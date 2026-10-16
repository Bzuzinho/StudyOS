<?php

namespace App\Services\Academic;

use App\Models\Course;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

class ConfirmedCurriculum
{
    private const PROGRAMMES = [
        '6709' => ['slug' => 'contabilidade-financeira-i', 'file' => 'contabilidade-financeira-i-programa.json'],
        '11909' => ['slug' => 'ingles-c1', 'file' => 'ingles-c1-programa.json'],
        '15436' => ['slug' => 'metodos-quantitativos', 'file' => 'metodos-quantitativos-programa.json'],
    ];

    public function apply(Course $course): void
    {
        $programme = self::PROGRAMMES[$course->academic_code] ?? null;
        if (! $programme) {
            return;
        }

        $outline = json_decode(file_get_contents(database_path('data/'.$programme['file'])), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($course, $programme, $outline) {
            $ids = [];
            foreach ($outline as $number => $title) {
                $number = (string) $number;
                $parentNumber = str_contains($number, '.') ? substr($number, 0, strrpos($number, '.')) : null;
                $topic = Topic::firstOrNew([
                    'course_id' => $course->id,
                    'source' => 'confirmed_programme',
                    'external_id' => $course->id.':'.$programme['slug'].':'.$number,
                ]);
                $topic->fill([
                    'title' => $title,
                    'parent_id' => $parentNumber === null ? null : ($ids[$parentNumber] ?? null),
                    'position' => count($ids) + 1,
                    'status' => 'active',
                    'metadata' => [
                        ...($topic->metadata ?? []),
                        'curricular_number' => $number,
                        'origin' => 'user_confirmed_programme',
                        'confirmed_on' => '2026-10-16',
                        'programme_type' => $course->academic_code === '11909' ? 'learning_objectives' : 'syllabus',
                    ],
                ])->save();
                $ids[$number] = $topic->id;
            }

            // Old topics remain in the database for materials, attempts and historical sessions.
            // Only the canonical confirmed programme must be active in the current UC tree.
            $course->topics()->whereNotIn('id', array_values($ids))
                ->where('status', 'active')->update(['status' => 'archived']);
        });
    }
}
