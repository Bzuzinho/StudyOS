<?php

namespace App\Services\Moodle;

use App\Models\Material;
use App\Models\SourceCourse;
use App\Models\SyncConnection;
use App\Models\SyncRun;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class MoodleSyncService
{
    public function __construct(
        private readonly MoodleAuthenticatedClient $client,
        private readonly MoodleHtmlParser $parser,
        private readonly MoodleFileSynchronizer $files,
    ) {}

    public function sync(SyncConnection $connection): SyncRun
    {
        $run = $connection->runs()->create([
            'status' => 'running',
            'started_at' => now(),
            'stats' => [],
        ]);

        $lockKey = 2026100614;
        $lockHeld = false;

        $stats = [
            'courses_scanned' => 0,
            'items_seen' => 0,
            'files_seen' => 0,
            'versions_created' => 0,
            'unchanged' => 0,
            'unsupported' => 0,
            'file_errors' => 0,
            'materials_marked_missing' => 0,
        ];

        try {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select('select pg_advisory_lock(?)', [$lockKey]);
                $lockHeld = true;
            }

            $this->client->login();

            $sourceCourses = SourceCourse::query()
                ->with('course')
                ->where('source', 'moodle')
                ->whereNotNull('course_id')
                ->orderBy('external_id')
                ->get();

            if ($sourceCourses->isEmpty()) {
                throw new RuntimeException('No Moodle course mappings are available.');
            }

            foreach ($sourceCourses as $sourceCourse) {
                if (! $sourceCourse->course || $sourceCourse->course->status !== 'active') {
                    continue;
                }

                $courseUrl = $this->client->url('/course/view.php?id='.$sourceCourse->external_id);
                $coursePage = $this->client->get($courseUrl);
                $items = $this->parser->courseItems(
                    $coursePage->body(),
                    $courseUrl,
                    $sourceCourse->external_id,
                );

                $expanded = [];

                foreach ($items as $item) {
                    $stats['items_seen']++;

                    if ($item['kind'] !== 'folder') {
                        $expanded[] = $item;
                        continue;
                    }

                    try {
                        $folderPage = $this->client->get($item['url']);
                        $folderFiles = $this->parser->folderFiles(
                            $folderPage->body(),
                            $item['url'],
                            $sourceCourse->external_id,
                            (string) $item['cmid'],
                        );

                        foreach ($folderFiles as $folderFile) {
                            $expanded[] = $folderFile;
                        }
                    } catch (Throwable) {
                        $stats['file_errors']++;
                    }
                }

                $seenExternalIds = [];

                foreach ($this->uniqueItems($expanded) as $item) {
                    $seenExternalIds[] = $item['external_id'];
                    $this->touchExisting($item['external_id'], $sourceCourse->external_id);
                    $stats['files_seen']++;

                    try {
                        $result = $this->files->sync(
                            $sourceCourse->course,
                            $sourceCourse->external_id,
                            $item,
                        );

                        match ($result['status']) {
                            'version_created' => $stats['versions_created']++,
                            'unchanged' => $stats['unchanged']++,
                            default => $stats['unsupported']++,
                        };
                    } catch (Throwable) {
                        $stats['file_errors']++;
                    }
                }

                $stats['materials_marked_missing'] += $this->markMissing(
                    $sourceCourse->course_id,
                    $sourceCourse->external_id,
                    $seenExternalIds,
                );

                $stats['courses_scanned']++;
            }

            $connection->update([
                'status' => $stats['file_errors'] > 0 ? 'success_with_warnings' : 'success',
                'last_synced_at' => now(),
            ]);

            $run->update([
                'status' => $stats['file_errors'] > 0 ? 'success_with_warnings' : 'success',
                'finished_at' => now(),
                'stats' => $stats,
                'error' => null,
            ]);

            return $run->fresh();
        } catch (Throwable $exception) {
            $connection->update(['status' => 'failed']);

            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'stats' => $stats,
                'error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            report($exception);

            return $run->fresh();
        } finally {
            if ($lockHeld) {
                DB::select('select pg_advisory_unlock(?)', [$lockKey]);
            }
        }
    }

    private function touchExisting(string $externalId, string $moodleCourseId): void
    {
        $material = Material::query()
            ->where('source', 'moodle')
            ->where('external_id', $externalId)
            ->first();

        if (! $material) {
            return;
        }

        $metadata = $material->metadata ?? [];
        $metadata['moodle_course_id'] = $moodleCourseId;
        $metadata['last_seen_at'] = now()->toIso8601String();
        $metadata['missing_count'] = 0;

        $material->update([
            'status' => 'active',
            'metadata' => $metadata,
        ]);
    }

    private function markMissing(
        int $courseId,
        string $moodleCourseId,
        array $seenExternalIds,
    ): int {
        $threshold = max(2, (int) config('studyos.moodle.missing_threshold', 2));
        $marked = 0;

        Material::query()
            ->where('source', 'moodle')
            ->where('course_id', $courseId)
            ->where(function ($query) use ($moodleCourseId) {
                $query->where('metadata->moodle_course_id', $moodleCourseId);
            })
            ->whereNotIn('external_id', $seenExternalIds ?: ['__none__'])
            ->get()
            ->each(function (Material $material) use ($threshold, &$marked) {
                $metadata = $material->metadata ?? [];
                $missingCount = ((int) ($metadata['missing_count'] ?? 0)) + 1;
                $metadata['missing_count'] = $missingCount;
                $metadata['last_missing_at'] = now()->toIso8601String();

                $updates = ['metadata' => $metadata];

                if ($missingCount >= $threshold) {
                    $updates['status'] = 'missing';
                    $marked++;
                }

                $material->update($updates);
            });

        return $marked;
    }

    private function uniqueItems(array $items): array
    {
        $unique = [];

        foreach ($items as $item) {
            $unique[$item['external_id']] = $item;
        }

        return array_values($unique);
    }
}
