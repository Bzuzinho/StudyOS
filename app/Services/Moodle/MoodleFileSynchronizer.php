<?php

namespace App\Services\Moodle;

use App\Jobs\ExtractMaterialVersion;
use App\Models\Course;
use App\Models\Material;
use App\Models\MaterialVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MoodleFileSynchronizer
{
    public function __construct(
        private readonly MoodleAuthenticatedClient $client,
        private readonly MoodleHtmlParser $parser,
    ) {}

    /**
     * @param array{kind:string,external_id:string,title:string,url:string,cmid:?string} $item
     * @return array{status:string,material_id:?int,version_id:?int}
     */
    public function sync(Course $course, string $moodleCourseId, array $item): array
    {
        if ($item['kind'] === 'folder') {
            throw new RuntimeException('Folder items must be expanded before file synchronization.');
        }

        $downloadUrl = $item['url'];

        if ($item['kind'] === 'resource') {
            $separator = str_contains($downloadUrl, '?') ? '&' : '?';
            $downloadUrl .= $separator.'redirect=1';
        }

        $download = $this->client->download($downloadUrl);

        try {
            if ($download['content_type'] === 'text/html') {
                $html = file_get_contents($download['path']);

                if ($html === false) {
                    throw new RuntimeException('Could not inspect Moodle resource HTML.');
                }

                $embedded = $this->parser->embeddedFiles($html, $item['url']);

                if ($embedded === []) {
                    return ['status' => 'unsupported', 'material_id' => null, 'version_id' => null];
                }

                $candidate = null;

                foreach ($embedded as $embeddedUrl) {
                    if ($this->extensionAllowed($this->filenameFromUrl($embeddedUrl))) {
                        $candidate = $embeddedUrl;
                        break;
                    }
                }

                if (! $candidate) {
                    return ['status' => 'unsupported', 'material_id' => null, 'version_id' => null];
                }

                @unlink($download['path']);
                $download = $this->client->download($candidate);
                $downloadUrl = $candidate;
            }

            $filename = $this->filename(
                $download['content_disposition'],
                $downloadUrl,
                $item['title'],
                $download['content_type'],
            );
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (! in_array($extension, config('studyos.moodle.allowed_extensions', []), true)) {
                return ['status' => 'unsupported', 'material_id' => null, 'version_id' => null];
            }

            $sha256 = hash_file('sha256', $download['path']);

            if (! is_string($sha256) || $sha256 === '') {
                throw new RuntimeException('Could not hash Moodle file.');
            }

            $material = Material::query()->firstOrNew([
                'source' => 'moodle',
                'external_id' => $item['external_id'],
            ]);

            $metadata = $material->metadata ?? [];
            $metadata = [
                ...$metadata,
                'moodle_course_id' => $moodleCourseId,
                'moodle_cmid' => $item['cmid'],
                'moodle_kind' => $item['kind'],
                'source_url' => $item['url'],
                'last_seen_at' => now()->toIso8601String(),
                'missing_count' => 0,
                'read_only_source' => true,
            ];

            $material->fill([
                'course_id' => $course->id,
                'type' => $extension === 'pptx' ? 'presentation' : 'document',
                'title' => $item['title'] !== '' ? $item['title'] : $filename,
                'url' => $item['url'],
                'status' => 'active',
                'metadata' => $metadata,
            ]);
            $material->save();

            $existing = MaterialVersion::query()
                ->where('material_id', $material->id)
                ->where('file_sha256', $sha256)
                ->first();

            if ($existing) {
                return [
                    'status' => 'unchanged',
                    'material_id' => $material->id,
                    'version_id' => $existing->id,
                ];
            }

            $storageDisk = config('filesystems.default', 'local');
            $safeFilename = Str::slug(pathinfo($filename, PATHINFO_FILENAME));

            if ($safeFilename === '') {
                $safeFilename = 'moodle-file';
            }

            $safeFilename .= '.'.$extension;
            $storagePath = 'materials/'.$course->id.'/moodle/'
                .hash('sha256', $item['external_id']).'/'.$sha256.'/'.$safeFilename;

            $stream = fopen($download['path'], 'rb');

            if (! is_resource($stream)) {
                throw new RuntimeException('Could not open downloaded Moodle file for cloud storage.');
            }

            try {
                Storage::disk($storageDisk)->writeStream(
                    $storagePath,
                    $stream,
                    ['visibility' => 'private'],
                );
            } finally {
                fclose($stream);
            }

            $version = DB::transaction(function () use (
                $material,
                $storageDisk,
                $storagePath,
                $filename,
                $download,
                $sha256,
                $extension,
                $item,
            ) {
                return MaterialVersion::query()->create([
                    'material_id' => $material->id,
                    'source_hash' => $sha256,
                    'version_label' => 'Moodle · '.now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                    'content_text' => null,
                    'manual_text' => null,
                    'storage_disk' => $storageDisk,
                    'storage_path' => $storagePath,
                    'original_filename' => $filename,
                    'mime_type' => $download['content_type'] ?: 'application/octet-stream',
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
                        'moodle_source_url' => $item['url'],
                    ],
                ]);
            });

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

    private function extensionAllowed(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, config('studyos.moodle.allowed_extensions', []), true);
    }

    private function filename(
        ?string $contentDisposition,
        string $url,
        string $fallback,
        string $contentType,
    ): string
    {
        if ($contentDisposition) {
            if (preg_match('/filename\*=UTF-8\'\'([^;]+)/i', $contentDisposition, $matches) === 1) {
                return rawurldecode(trim($matches[1], " \t\n\r\0\x0B\""));
            }

            if (preg_match('/filename="?([^";]+)"?/i', $contentDisposition, $matches) === 1) {
                return trim($matches[1]);
            }
        }

        $fromUrl = $this->filenameFromUrl($url);

        if (pathinfo($fromUrl, PATHINFO_EXTENSION) !== '') {
            return $fromUrl;
        }

        $fallback = trim($fallback) !== '' ? trim($fallback) : 'moodle-file';

        if (pathinfo($fallback, PATHINFO_EXTENSION) !== '') {
            return $fallback;
        }

        $extension = match (strtolower($contentType)) {
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt',
            'text/markdown' => 'md',
            default => null,
        };

        return $extension ? $fallback.'.'.$extension : $fallback;
    }

    private function filenameFromUrl(string $url): string
    {
        $parts = parse_url($url);
        $path = rawurldecode((string) ($parts['path'] ?? ''));

        return basename($path);
    }
}
