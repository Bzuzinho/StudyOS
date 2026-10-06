<?php

namespace App\Services\Moodle;

use App\Jobs\ExtractMaterialVersion;
use App\Models\Course;
use App\Models\Material;
use App\Models\MaterialVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MoodleApiFileSynchronizer
{
    public function __construct(
        private readonly MoodleWebServiceClient $client,
    ) {}

    /**
     * @return array{status:string,material_id:?int,version_id:?int}
     */
    public function sync(Course $course, string $moodleCourseId, array $item): array
    {
        $extension = strtolower(pathinfo($item['filename'], PATHINFO_EXTENSION));

        if (! in_array($extension, config('studyos.moodle.allowed_extensions', []), true)) {
            return ['status' => 'unsupported', 'material_id' => null, 'version_id' => null];
        }

        $download = $this->client->download($item['file_url']);

        try {
            $sha256 = hash_file('sha256', $download['path']);

            if (! is_string($sha256) || $sha256 === '') {
                throw new RuntimeException('Could not hash Moodle file.');
            }

            $material = Material::query()->firstOrNew([
                'source' => 'moodle',
                'external_id' => $item['external_id'],
            ]);

            $metadata = [
                ...($material->metadata ?? []),
                'moodle_course_id' => $moodleCourseId,
                'moodle_module_id' => $item['module_id'],
                'moodle_module_name' => $item['module_name'],
                'moodle_modname' => $item['modname'],
                'moodle_section_id' => $item['section_id'],
                'moodle_section_name' => $item['section_name'],
                'source_url' => $item['file_url'],
                'source_timemodified' => $item['timemodified'],
                'last_seen_at' => now()->toIso8601String(),
                'missing_count' => 0,
                'read_only_source' => true,
                'collection_mode' => 'moodle_webservice_token',
            ];

            $material->fill([
                'course_id' => $course->id,
                'type' => $extension === 'pptx' ? 'presentation' : 'document',
                'title' => $item['title'],
                'url' => $item['file_url'],
                'status' => 'active',
                'metadata' => $metadata,
            ]);
            $material->save();

            $existing = MaterialVersion::query()
                ->where('material_id', $material->id)
                ->where('file_sha256', $sha256)
                ->first();

            if ($existing) {
                $this->recoverQueuedExtraction($existing);

                return [
                    'status' => 'unchanged',
                    'material_id' => $material->id,
                    'version_id' => $existing->id,
                ];
            }

            $storageDisk = config('filesystems.default', 'local');
            $safeBase = Str::slug(pathinfo($item['filename'], PATHINFO_FILENAME)) ?: 'moodle-file';
            $safeFilename = $safeBase.'.'.$extension;
            $storagePath = 'materials/'.$course->id.'/moodle/'
                .hash('sha256', $item['external_id']).'/'.$sha256.'/'.$safeFilename;

            $stream = fopen($download['path'], 'rb');

            if (! is_resource($stream)) {
                throw new RuntimeException('Could not open downloaded Moodle file for cloud storage.');
            }

            try {
                $stored = Storage::disk($storageDisk)->writeStream(
                    $storagePath,
                    $stream,
                    ['visibility' => 'private'],
                );

                if (! $stored) {
                    throw new RuntimeException('Could not store the Moodle file in academic cloud storage.');
                }
            } finally {
                fclose($stream);
            }

            try {
                $version = DB::transaction(function () use (
                    $material,
                    $storageDisk,
                    $storagePath,
                    $item,
                    $download,
                    $sha256,
                    $extension,
                ) {
                    return MaterialVersion::query()->create([
                        'material_id' => $material->id,
                        'source_hash' => $sha256,
                        'version_label' => 'Moodle · '.now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                        'content_text' => null,
                        'manual_text' => null,
                        'storage_disk' => $storageDisk,
                        'storage_path' => $storagePath,
                        'original_filename' => $item['filename'],
                        'mime_type' => $download['content_type']
                            ?: ($item['mime_type'] ?: 'application/octet-stream'),
                        'size_bytes' => $download['size'],
                        'file_sha256' => $sha256,
                        'extraction_status' => 'queued',
                        'extraction_error' => null,
                        'extraction_queued_at' => now(),
                        'extraction_started_at' => null,
                        'extraction_finished_at' => null,
                        'extraction_attempts' => 0,
                        'observed_at' => now(),
                        'metadata' => [
                            'manual' => false,
                            'sections' => [],
                            'manual_text_supplied' => false,
                            'extraction_engine' => null,
                            'file_extension' => $extension,
                            'source' => 'moodle',
                            'moodle_external_id' => $item['external_id'],
                            'moodle_module_id' => $item['module_id'],
                            'moodle_section_name' => $item['section_name'],
                            'moodle_last_dispatch_at' => now()->toIso8601String(),
                        ],
                    ]);
                });
            } catch (Throwable $exception) {
                Storage::disk($storageDisk)->delete($storagePath);
                throw $exception;
            }

            ExtractMaterialVersion::dispatch($version->id)->afterCommit();

            return [
                'status' => 'version_created',
                'material_id' => $material->id,
                'version_id' => $version->id,
            ];
        } finally {
            @unlink($download['path']);
        }
    }

    private function recoverQueuedExtraction(MaterialVersion $version): void
    {
        if ($version->extraction_status !== 'queued') {
            return;
        }

        $lastDispatch = $version->metadata['moodle_last_dispatch_at'] ?? null;
        $dispatchAgain = ! $lastDispatch
            || Carbon::parse($lastDispatch)->lte(now()->subMinutes(10));

        if (! $dispatchAgain) {
            return;
        }

        $metadata = $version->metadata ?? [];
        $metadata['moodle_last_dispatch_at'] = now()->toIso8601String();
        $version->update(['metadata' => $metadata]);

        ExtractMaterialVersion::dispatch($version->id)->afterCommit();
    }
}
