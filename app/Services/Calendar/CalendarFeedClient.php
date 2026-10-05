<?php

namespace App\Services\Calendar;

use App\Models\SyncConnection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CalendarFeedClient
{
    public function fetch(SyncConnection $connection): array
    {
        $url = trim((string) $connection->secret);
        $this->assertAllowedUrl($url);

        $headers = ['Accept' => 'text/calendar, text/plain;q=0.9, */*;q=0.1'];
        $config = $connection->config ?? [];

        if (! empty($config['etag'])) {
            $headers['If-None-Match'] = $config['etag'];
        }

        if (! empty($config['last_modified'])) {
            $headers['If-Modified-Since'] = $config['last_modified'];
        }

        /** @var Response $response */
        $response = Http::withHeaders($headers)
            ->connectTimeout(10)
            ->timeout(25)
            ->retry(2, 400, throw: false)
            ->withOptions(['allow_redirects' => false])
            ->get($url);

        if ($response->status() === 304) {
            return ['not_modified' => true];
        }

        if ($response->redirect()) {
            throw new RuntimeException('Calendar feed redirects are not accepted.');
        }

        $response->throw();

        $body = $response->body();

        if (! str_contains($body, 'BEGIN:VCALENDAR')) {
            throw new RuntimeException('The remote source did not return a valid iCalendar document.');
        }

        return [
            'not_modified' => false,
            'content' => $body,
            'etag' => $response->header('ETag'),
            'last_modified' => $response->header('Last-Modified'),
            'content_hash' => hash('sha256', $body),
        ];
    }

    private function assertAllowedUrl(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            throw new RuntimeException('Calendar source must be a valid HTTPS URL.');
        }

        $host = strtolower($parts['host']);
        $allowed = config('studyos.calendar.allowed_hosts', []);

        $matches = collect($allowed)->contains(function (string $candidate) use ($host) {
            $candidate = strtolower(trim($candidate));

            return $candidate !== ''
                && ($host === $candidate || str_ends_with($host, '.'.$candidate));
        });

        if (! $matches) {
            throw new RuntimeException("Calendar host [{$host}] is not in the StudyOS allow-list.");
        }
    }
}
