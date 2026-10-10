<?php

namespace App\Services\Academic;

use App\Models\MaterialVersion;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

/**
 * Extracts a verified, hierarchical curricular outline exclusively from a
 * document explicitly identified as a UC programme/FUC, never Moodle folders.
 */
class FucCurriculumImporter
{
    public function import(MaterialVersion $version): array
    {
        $version->loadMissing('material');
        $material = $version->material;
        $name = mb_strtolower(trim(($material?->title ?? '').' '.($version->original_filename ?? '')));
        $text = (string) $version->content_text;

        if (! $material?->course_id
            || ! preg_match('/\b(fuc|programa(?:\s+e\s+orienta[cç][oõ]es)?(?:\s+da\s+unidade\s+curricular)?)\b/iu', $name)
            || ! preg_match('/\bprograma\s+anal[ií]tico\b/iu', $text)) {
            return ['recognized' => false, 'created' => 0, 'updated' => 0];
        }

        $afterHeading = preg_split('/\b(?:4\.?\s*)?Programa\s+anal[ií]tico\b/iu', $text, 2);
        if (count($afterHeading) !== 2) {
            return ['recognized' => false, 'created' => 0, 'updated' => 0];
        }

        $outline = preg_split('/\n\s*(?:5\.?\s*)?Metodologias\s+de\s+ensino\b/iu', $afterHeading[1], 2)[0];
        $items = [];
        foreach (preg_split('/\R/u', $outline) ?: [] as $line) {
            $line = trim($line);
            if (! preg_match('/^(\d+(?:\.\d+){0,3})\.\s+(.+)$/u', $line, $match)) {
                continue;
            }
            $number = $match[1];
            $title = trim($match[2]);
            if (mb_strlen($title) < 4 || mb_strlen($title) > 240) {
                continue;
            }
            $root = (int) explode('.', $number)[0];
            if ($root < 1 || $root > 30) {
                continue;
            }
            $items[$number] = $title;
        }

        // A single stray enumerated item is not sufficient evidence of a FUC.
        if (count($items) < 3 || ! collect(array_keys($items))->contains(fn ($n) => substr_count($n, '.') >= 1)) {
            return ['recognized' => false, 'created' => 0, 'updated' => 0];
        }

        return DB::transaction(function () use ($material, $version, $items) {
            $ids = [];
            $created = 0;
            $updated = 0;
            foreach ($items as $number => $title) {
                $pieces = explode('.', $number);
                array_pop($pieces);
                $parent = implode('.', $pieces);
                $externalId = $material->course_id.':'.$number;
                $topic = Topic::query()->firstOrNew([
                    'source' => 'official_fuc',
                    'external_id' => $externalId,
                ]);
                $wasExisting = $topic->exists;
                $topic->fill([
                    'course_id' => $material->course_id,
                    'parent_id' => $ids[$parent] ?? null,
                    'title' => $title,
                    'position' => count($ids) + 1,
                    'status' => 'active',
                    'metadata' => [
                        ...($topic->metadata ?? []),
                        'curricular_number' => $number,
                        'official_syllabus_verified' => true,
                        'material_id' => $material->id,
                        'material_version_id' => $version->id,
                        'source_document' => $version->original_filename ?? $material->title,
                    ],
                ]);
                $topic->save();
                $ids[$number] = $topic->id;
                $wasExisting ? $updated++ : $created++;
            }

            // These were Moodle folder labels, not syllabus content. Preserve records and links.
            Topic::query()->where('course_id', $material->course_id)
                ->where('source', 'source_backed')
                ->where('metadata->origin', 'moodle_section')
                ->where('status', 'active')
                ->update(['status' => 'archived']);

            return ['recognized' => true, 'created' => $created, 'updated' => $updated];
        });
    }
}
