<?php

namespace App\Services\Moodle;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

class MoodleAuthenticatedClient
{
    private CookieJar $cookies;

    private string $baseUrl;

    private string $baseHost;

    private string $basePath;

    private bool $authenticated = false;

    public function __construct()
    {
        $this->cookies = new CookieJar();
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
        return trim((string) config('studyos.moodle.username')) !== ''
            && (string) config('studyos.moodle.password') !== '';
    }

    public function login(): void
    {
        if ($this->authenticated) {
            return;
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException('Moodle credentials are not configured.');
        }

        $loginUrl = $this->url('/login/index.php');
        $loginPage = $this->get($loginUrl, authenticate: false);

        $crawler = new Crawler($loginPage->body(), $loginUrl);
        $token = null;

        if ($crawler->filter('input[name="logintoken"]')->count() > 0) {
            $token = $crawler->filter('input[name="logintoken"]')->first()->attr('value');
        }

        $payload = [
            'username' => (string) config('studyos.moodle.username'),
            'password' => (string) config('studyos.moodle.password'),
            'anchor' => '',
        ];

        if (is_string($token) && $token !== '') {
            $payload['logintoken'] = $token;
        }

        $response = Http::withOptions($this->httpOptions())
            ->asForm()
            ->timeout(45)
            ->post($loginUrl, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Moodle login returned HTTP '.$response->status().'.');
        }

        if ($this->looksLikeLoginPage($response->body())) {
            throw new RuntimeException('Moodle authentication failed. Check the configured credentials.');
        }

        $this->authenticated = true;

        $courses = $this->get($this->url('/my/courses.php'));

        if (! str_contains($courses->body(), '/course/view.php?id=')) {
            $this->authenticated = false;
            throw new RuntimeException(
                'Moodle login succeeded but the courses page is not available. An interactive policy or SSO step may still be required.'
            );
        }
    }

    public function get(string $url, bool $authenticate = true): Response
    {
        if ($authenticate) {
            $this->login();
        }

        $url = $this->assertAllowedUrl($url);

        $response = Http::withOptions($this->httpOptions())
            ->timeout(60)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Moodle request returned HTTP '.$response->status().'.');
        }

        if ($authenticate && $this->looksLikeLoginPage($response->body())) {
            $this->authenticated = false;
            throw new RuntimeException('Moodle session expired during synchronization.');
        }

        return $response;
    }

    /**
     * @return array{path:string,content_type:string,content_disposition:?string,size:int}
     */
    public function download(string $url): array
    {
        $this->login();
        $url = $this->assertAllowedUrl($url);
        $path = tempnam(sys_get_temp_dir(), 'studyos-moodle-');

        if ($path === false) {
            throw new RuntimeException('Could not create a temporary Moodle download file.');
        }

        try {
            $response = Http::withOptions([
                ...$this->httpOptions(),
                'sink' => $path,
            ])->timeout(120)->get($url);

            if (! $response->successful()) {
                throw new RuntimeException('Moodle download returned HTTP '.$response->status().'.');
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
                $html = file_get_contents($path);

                if ($html !== false && $this->looksLikeLoginPage($html)) {
                    $this->authenticated = false;
                    throw new RuntimeException('Moodle session expired during file download.');
                }
            }

            return [
                'path' => $path,
                'content_type' => $contentType,
                'content_disposition' => $response->header('Content-Disposition'),
                'size' => (int) $size,
            ];
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        }
    }

    public function url(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $this->assertAllowedUrl($path);
        }

        return $this->assertAllowedUrl(
            $this->baseUrl.'/'.ltrim($path, '/'),
        );
    }

    private function assertAllowedUrl(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('Invalid Moodle URL.');
        }

        if (strtolower((string) $parts['scheme']) !== 'https') {
            throw new RuntimeException('Moodle collector refuses non-HTTPS URLs.');
        }

        if (strtolower((string) $parts['host']) !== $this->baseHost) {
            throw new RuntimeException('Moodle collector refused a cross-host URL.');
        }

        $path = (string) ($parts['path'] ?? '');

        if ($this->basePath !== '' && ! str_starts_with($path, $this->basePath.'/') && $path !== $this->basePath) {
            throw new RuntimeException('Moodle collector refused a URL outside the configured academic-year path.');
        }

        return $url;
    }

    private function httpOptions(): array
    {
        return [
            'cookies' => $this->cookies,
            'allow_redirects' => [
                'max' => 8,
                'strict' => true,
                'referer' => true,
                'on_redirect' => function ($request, $response, $uri): void {
                    $this->assertAllowedUrl((string) $uri);
                },
            ],
            'headers' => [
                'User-Agent' => 'StudyOS/1.0 Moodle read-only sync',
                'Accept-Language' => 'pt-PT,pt;q=0.9,en;q=0.7',
            ],
        ];
    }

    private function looksLikeLoginPage(string $html): bool
    {
        if ($html === '') {
            return false;
        }

        try {
            $crawler = new Crawler($html);

            return $crawler->filter('form#login, form[action*="/login/index.php"] input[name="username"]')->count() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
