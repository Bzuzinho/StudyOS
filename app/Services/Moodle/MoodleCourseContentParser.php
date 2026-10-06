<?php

namespace App\Services\Moodle;

class MoodleCourseContentParser
{
    /**
     * @return list<array{
     *   external_id:string,
     *   title:string,
     *   filename:string,
     *   file_url:string,
     *   mime_type:?string,
     *   size_bytes:?int,
     *   module_id:string,
     *   module_name:string,
     *   modname:?string,
     *   section_id:?string,
     *   section_name:?string,
     *   timemodified:?int
     * }>
     */
    public function files(array $sections, string $courseId): array
    {
        $files = [];

        foreach ($sections as $section) {
            $sectionId = isset($section['id']) ? (string) $section['id'] : null;
            $sectionName = trim((string) ($section['name'] ?? ''));

            foreach (($section['modules'] ?? []) as $module) {
                $moduleId = (string) ($module['id'] ?? '');

                if ($moduleId === '') {
                    continue;
                }

                $moduleName = trim((string) ($module['name'] ?? ''));
                $contents = array_values(array_filter(
                    $module['contents'] ?? [],
                    fn ($content) => is_array($content)
                        && ($content['type'] ?? null) === 'file'
                        && ! empty($content['fileurl']),
                ));

                foreach ($contents as $content) {
                    $fileUrl = (string) $content['fileurl'];
                    $filename = trim((string) ($content['filename'] ?? $this->filenameFromUrl($fileUrl)));

                    if ($filename === '') {
                        $filename = $this->filenameFromUrl($fileUrl);
                    }

                    $canonicalPath = $this->canonicalPath($fileUrl);
                    $title = count($contents) === 1 && $moduleName !== ''
                        ? $moduleName
                        : trim(($moduleName !== '' ? $moduleName.' · ' : '').$filename);

                    $externalId = "course:{$courseId}:module:{$moduleId}:file:"
                        .hash('sha256', $canonicalPath);

                    $files[$externalId] = [
                        'external_id' => $externalId,
                        'title' => $title !== '' ? $title : $filename,
                        'filename' => $filename,
                        'file_url' => $fileUrl,
                        'mime_type' => isset($content['mimetype']) ? (string) $content['mimetype'] : null,
                        'size_bytes' => isset($content['filesize']) ? (int) $content['filesize'] : null,
                        'module_id' => $moduleId,
                        'module_name' => $moduleName,
                        'modname' => isset($module['modname']) ? (string) $module['modname'] : null,
                        'section_id' => $sectionId,
                        'section_name' => $sectionName !== '' ? $sectionName : null,
                        'timemodified' => isset($content['timemodified']) ? (int) $content['timemodified'] : null,
                    ];
                }
            }
        }

        return array_values($files);
    }

    private function canonicalPath(string $url): string
    {
        $parts = parse_url($url);

        return rawurldecode((string) ($parts['path'] ?? $url));
    }

    private function filenameFromUrl(string $url): string
    {
        $parts = parse_url($url);

        return basename(rawurldecode((string) ($parts['path'] ?? '')));
    }
}
