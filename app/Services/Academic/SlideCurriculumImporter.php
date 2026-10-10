<?php
namespace App\Services\Academic;

use App\Models\MaterialVersion;
use App\Models\Topic;

class SlideCurriculumImporter
{
    public function import(MaterialVersion $version): array
    {
        $version->loadMissing('material');
        $courseId = $version->material?->course_id;
        $sections = $version->metadata['sections'] ?? [];
        $extension = strtolower(pathinfo((string) $version->original_filename, PATHINFO_EXTENSION));
        if (! $courseId || ! in_array($extension, ['pptx', 'pdf'], true) || ! is_array($sections) || ! $version->content_text) {
            return ['created' => 0, 'updated' => 0];
        }

        $stats = ['created' => 0, 'updated' => 0];
        foreach ($sections as $section) {
            if (! preg_match('/^(?:Slide|Página) ([0-9]+)$/u', (string) ($section['locator'] ?? ''), $m)) {
                continue;
            }
            $start = (int) ($section['start'] ?? -1);
            $length = (int) ($section['length'] ?? 0);
            if ($start < 0 || $length < 12) {
                continue;
            }
            $body = trim(mb_substr($version->content_text, $start, $length));
            $heading = trim(explode("\n", $body)[0]);
            if (mb_strlen($heading) < 3) {
                continue;
            }
            $number = (int) $m[1];
            if ($extension === 'pdf' && ! preg_match('/slide|diapositivo/i', ($version->material?->title ?? '').' '.($version->original_filename ?? ''))) {
                continue;
            }
            $topic = Topic::firstOrNew([
                'source' => 'moodle_slide',
                'external_id' => $version->material_id.':slide:'.$number,
            ]);
            $existing = $topic->exists;
            $topic->fill([
                'course_id' => $courseId,
                'title' => 'Slide '.$number.' · '.mb_substr($heading, 0, 120),
                'position' => $number,
                'status' => 'active',
                'metadata' => [
                    'provisional' => true,
                    'official_syllabus_verified' => false,
                    'origin' => 'moodle_slide',
                    'material_id' => $version->material_id,
                    'material_version_id' => $version->id,
                    'slide_number' => $number,
                ],
            ])->save();
            $locator = $extension === 'pdf' ? 'Página '.$number : 'Slide '.$number;
            $chunks = $version->sourceChunks()->where('status', 'active')
                ->where(function ($query) use ($locator) {
                    $query->where('locator', $locator)
                        ->orWhere('locator', 'like', $locator.' ·%');
                })->pluck('id')->all();
            if ($chunks !== []) {
                $topic->sourceChunks()->syncWithoutDetaching(
                    array_fill_keys($chunks, ['match_method' => 'slide_locator', 'confidence' => 1.0])
                );
            }
            $stats[$existing ? 'updated' : 'created']++;
        }
        return $stats;
    }
}
