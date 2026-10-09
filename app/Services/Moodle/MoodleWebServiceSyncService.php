<?php

namespace App\Services\Moodle;

use App\Models\Material;
use App\Services\Academic\SourceBackedTopicBuilder;
use App\Models\SourceCourse;
use App\Models\SyncConnection;
use App\Models\SyncRun;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class MoodleWebServiceSyncService
{
    public function __construct(
        private readonly MoodleWebServiceClient $client,
        private readonly MoodleCourseContentParser $parser,
        private readonly MoodleApiFileSynchronizer $files,
        private readonly SourceBackedTopicBuilder $topics,
    ) {}

    public function sync(SyncConnection $connection, ?SyncRun $run = null): SyncRun
    {
        $run ??= $connection->runs()->create([
            'status' => 'running',
            'started_at' => now(),
            'stats' => [],
        ]);
        $run->update(['status' => 'running', 'started_at' => now()]);

        $lockKey = 2026100615;
        $lockHeld = false;
        $stats = [
            'courses_scanned' => 0,
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

            $siteInfo = $this->client->siteInfo();
            $availableFunctions = collect($siteInfo['functions'] ?? [])
                ->pluck('name')
                ->filter()
                ->values();

            if ($availableFunctions->isNotEmpty()
                && ! $availableFunctions->contains('core_course_get_contents')) {
                throw new RuntimeException(
                    'The Moodle mobile service token does not expose core_course_get_contents.'
                );
            }

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

                $sections = $this->client->courseContents($sourceCourse->external_id);
                $items = $this->parser->files($sections, $sourceCourse->external_id);
                $seenExternalIds = [];

                foreach ($items as $item) {
                    $seenExternalIds[] = $item['external_id'];
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
                    } catch (Throwable $exception) {
                        $stats['file_errors']++;
                        report($exception);
                    }
                }

                $stats['materials_marked_missing'] += $this->markMissing(
                    $sourceCourse->course_id,
                    $sourceCourse->external_id,
                    $seenExternalIds,
                );

                // Build provisional topics only from sections and summaries actually observed.
                $this->topics->build($sourceCourse->course);
                $stats['courses_scanned']++;
            }

            $status = $stats['file_errors'] > 0 ? 'success_with_warnings' : 'success';
            $connection->update([
                'status' => $status,
                'last_synced_at' => now(),
                'config' => [
                    ...($connection->config ?? []),
                    'auth_mode' => 'microsoft_sso_moodle_token',
                    'site_name' => $siteInfo['sitename'] ?? null,
                    'user_fullname' => $siteInfo['fullname'] ?? null,
                    'user_id' => $siteInfo['userid'] ?? null,
                    'last_verified_at' => now()->toIso8601String(),
                ],
            ]);

            $run->update([
                'status' => $status,
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
            ->where('metadata->moodle_course_id', $moodleCourseId)
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
}
