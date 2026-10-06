<?php

namespace App\Services\Moodle;

use Symfony\Component\DomCrawler\Crawler;

class MoodleHtmlParser
{
    /**
     * @return list<array{kind:string,external_id:string,title:string,url:string,cmid:?string}>
     */
    public function courseItems(string $html, string $pageUrl, string $courseId): array
    {
        $crawler = new Crawler($html, $pageUrl);
        $items = [];

        $crawler->filter('a[href]')->each(function (Crawler $node) use (&$items, $courseId) {
            $url = $node->link()->getUri();
            $parts = parse_url($url);

            if (! is_array($parts)) {
                return;
            }

            $path = (string) ($parts['path'] ?? '');
            parse_str((string) ($parts['query'] ?? ''), $query);
            $cmid = isset($query['id']) ? (string) $query['id'] : null;
            $title = $this->cleanTitle($node->text(''));

            if (str_contains($path, '/mod/resource/view.php') && $cmid) {
                $items[] = [
                    'kind' => 'resource',
                    'external_id' => "course:{$courseId}:resource:{$cmid}",
                    'title' => $title !== '' ? $title : 'Recurso Moodle',
                    'url' => $url,
                    'cmid' => $cmid,
                ];

                return;
            }

            if (str_contains($path, '/mod/folder/view.php') && $cmid) {
                $items[] = [
                    'kind' => 'folder',
                    'external_id' => "course:{$courseId}:folder:{$cmid}",
                    'title' => $title !== '' ? $title : 'Pasta Moodle',
                    'url' => $url,
                    'cmid' => $cmid,
                ];

                return;
            }

            if (str_contains($path, '/pluginfile.php/')) {
                $canonical = $this->canonicalPluginfilePath($url);
                $items[] = [
                    'kind' => 'file',
                    'external_id' => "course:{$courseId}:pluginfile:".hash('sha256', $canonical),
                    'title' => $title !== '' ? $title : $this->filenameFromUrl($url),
                    'url' => $url,
                    'cmid' => null,
                ];
            }
        });

        return $this->uniqueByExternalId($items);
    }

    /**
     * @return list<array{kind:string,external_id:string,title:string,url:string,cmid:?string}>
     */
    public function folderFiles(
        string $html,
        string $pageUrl,
        string $courseId,
        string $cmid,
    ): array {
        $crawler = new Crawler($html, $pageUrl);
        $items = [];

        $this->collectPluginfileAttributes($crawler, function (string $url) use (&$items, $courseId, $cmid) {
            $canonical = $this->canonicalPluginfilePath($url);
            $items[] = [
                'kind' => 'file',
                'external_id' => "course:{$courseId}:folder:{$cmid}:file:".hash('sha256', $canonical),
                'title' => $this->filenameFromUrl($url),
                'url' => $url,
                'cmid' => $cmid,
            ];
        });

        return $this->uniqueByExternalId($items);
    }

    /**
     * @return list<string>
     */
    public function embeddedFiles(string $html, string $pageUrl): array
    {
        $crawler = new Crawler($html, $pageUrl);
        $urls = [];

        $this->collectPluginfileAttributes($crawler, function (string $url) use (&$urls) {
            $urls[$this->canonicalPluginfilePath($url)] = $url;
        });

        return array_values($urls);
    }

    private function collectPluginfileAttributes(Crawler $crawler, callable $consumer): void
    {
        foreach (['a[href]' => 'href', '[src]' => 'src', '[data]' => 'data'] as $selector => $attribute) {
            $crawler->filter($selector)->each(function (Crawler $node) use ($attribute, $consumer) {
                $value = $node->attr($attribute);

                if (! is_string($value) || $value === '' || ! str_contains($value, '/pluginfile.php/')) {
                    return;
                }

                try {
                    $url = $attribute === 'href'
                        ? $node->link()->getUri()
                        : $this->resolveUrl($node->getUri(), $value);

                    $consumer($url);
                } catch (\Throwable) {
                    // Ignore malformed markup; a later complete sync can still find the resource.
                }
            });
        }
    }

    private function resolveUrl(string $baseUrl, string $value): string
    {
        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        $base = parse_url($baseUrl);

        if (! is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            return $value;
        }

        if (str_starts_with($value, '/')) {
            return $base['scheme'].'://'.$base['host'].$value;
        }

        $directory = rtrim(dirname((string) ($base['path'] ?? '/')), '/');

        return $base['scheme'].'://'.$base['host'].$directory.'/'.$value;
    }

    private function canonicalPluginfilePath(string $url): string
    {
        $parts = parse_url($url);

        return rawurldecode((string) ($parts['path'] ?? $url));
    }

    private function filenameFromUrl(string $url): string
    {
        $parts = parse_url($url);
        $path = rawurldecode((string) ($parts['path'] ?? ''));
        $filename = basename($path);

        return $filename !== '' ? $filename : 'Ficheiro Moodle';
    }

    private function cleanTitle(string $title): string
    {
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));
        $title = preg_replace('/\s+(File|Folder|Ficheiro|Pasta)$/iu', '', $title) ?? $title;

        return trim($title);
    }

    private function uniqueByExternalId(array $items): array
    {
        $unique = [];

        foreach ($items as $item) {
            $unique[$item['external_id']] = $item;
        }

        return array_values($unique);
    }
}
