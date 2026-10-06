<?php

namespace App\Services\Moodle;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class MoodleWebServiceClient
{
    private string $baseUrl;

    private string $baseHost;

    private string $basePath;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('studyos.moodle.base_url'), '/');
        $parts = parse_url($this->baseUrl);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('MOODLE_BASE_URL is invalid.');
        }

        if (strtolower((string) $parts['scheme']) !== 'https') {
            throw new RuntimeException('Moodle synchronization requires HTTPS.');
        }

        $this->baseHost = strtolower((string) $parts['host']);
        $this->basePath = rtrim((string) ($parts['path'] ?? ''), '/');
    }

    public function isConfigured(): bool
    {
        return trim((string) config('studyos.moodle.token')) !== '';
    }

    public function siteInfo(): array
    {
        return $this->call('core_webservice_get_site_info');
    }

    public function courseContents(string|int $courseId): array
    {
        return $this->call('core_course_get_contents', [
            'courseid' => (int) $courseId,
        ]);
    }

    public function call(string $function, array $parameters = []): array
    {
        $token = trim((string) config('studyos.moodle.token'));

        if ($token === '') {
            throw new RuntimeException('Moodle SSO token is not configured.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(60)
            ->post($this->baseUrl.'/webservice/rest/server.php', [
                'wstoken' => $token,
                'wsfunction' => $function,
                'moodlewsrestformat' => 'json',
                ...$parameters,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Moodle web service returned HTTP {$response->status()}.");
        }

        try {
            $data = $response->json();
        } catch (Throwable) {
            throw new RuntimeException('Moodle web service returned an invalid JSON response.');
        }

        if (! is_array($data)) {
            throw new RuntimeException('Moodle web service returned an unexpected response.');
        }

        if (isset($data['exception']) || isset($data['errorcode'])) {
            $message = trim((string) ($data['message'] ?? 'Moodle web service rejected the request.'));
            throw new RuntimeException($message !== '' ? $message : 'Moodle web service rejected the request.');
        }

        return $data;
    }

    /**
     * @return array{path:string,content_type:string,content_disposition:?string,size:int}
     */
    public function download(string $fileUrl): array
    {
        $token = trim((string) config('studyos.moodle.token'));

        if ($token === '') {
            throw new RuntimeException('Moodle SSO token is not configured.');
        }

        $fileUrl = $this->assertAllowedFileUrl($fileUrl);
        $parts = parse_url($fileUrl);
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['token'] = $token;

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        $url = ($parts['scheme'] ?? 'https').'://'.$parts['host']
            .$port
            .($parts['path'] ?? '')
            .'?'.http_build_query($query);

        $path = tempnam(sys_get_temp_dir(), 'studyos-moodle-api-');

        if ($path === false) {
            throw new RuntimeException('Could not create a temporary Moodle file.');
        }

        try {
            $response = Http::withOptions(['sink' => $path])
                ->timeout(120)
                ->get($url);

            if (! $response->successful()) {
                throw new RuntimeException("Moodle file download returned HTTP {$response->status()}.");
            }

            $size = filesize($path);

            if ($size === false) {
                throw new RuntimeException('Could not inspect the downloaded Moodle file.');
            }

            $maxBytes = max(1, (int) config('studyos.moodle.max_file_bytes', 26214400));

            if ($size > $maxBytes) {
                throw new RuntimeException('Moodle file exceeds the configured size limit.');
            }

            $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

            if ($contentType === 'text/html') {
                throw new RuntimeException('Moodle returned HTML instead of the requested academic file.');
            }

            return [
                'path' => $path,
                'content_type' => $contentType,
                'content_disposition' => $response->header('Content-Disposition'),
                'size' => (int) $size,
            ];
        } catch (Throwable $exception) {
            @unlink($path);
            throw $exception;
        }
    }

    private function assertAllowedFileUrl(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('Invalid Moodle file URL.');
        }

        if (strtolower((string) $parts['scheme']) !== 'https') {
            throw new RuntimeException('Moodle file download requires HTTPS.');
        }

        if (strtolower((string) $parts['host']) !== $this->baseHost) {
            throw new RuntimeException('Moodle collector refused a cross-host file URL.');
        }

        $path = (string) ($parts['path'] ?? '');

        if ($this->basePath !== '' && ! str_starts_with($path, $this->basePath.'/')) {
            throw new RuntimeException('Moodle collector refused a file URL outside the configured academic-year path.');
        }

        return $url;
    }
}
