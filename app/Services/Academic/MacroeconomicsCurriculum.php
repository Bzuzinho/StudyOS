<?php

namespace App\Services\Academic;

use App\Models\Course;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

class MacroeconomicsCurriculum
{
    public function apply(Course $course): void
    {
        $outline = json_decode(file_get_contents(database_path('data/macroeconomia-programa.json')), true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($course, $outline) {
            $ids = [];
            foreach ($outline as $number => $title) {
                $number = (string) $number;
                $parent = str_contains($number, '.') ? explode('.', $number)[0] : null;
                // Separate identity: never reuse an unrelated topic's number or learning evidence.
                $topic = Topic::firstOrNew([
                    'course_id' => $course->id,
                    'source' => 'confirmed_programme',
                    'external_id' => $course->id.':macro:'.$number,
                ]);
                $topic->fill([
                    'title' => $title,
                    'parent_id' => $parent === null ? null : $ids[$parent],
                    'position' => count($ids) + 1,
                    'status' => 'active',
                    'metadata' => [
                        ...($topic->metadata ?? []),
                        'curricular_number' => $number,
                        'official_syllabus_verified' => true,
                        'origin' => 'user_confirmed_programme',
                        'confirmed_on' => '2026-10-15',
                    ],
                ])->save();
                $ids[$number] = $topic->id;
            }
            // Keep previous materials, exercises and study history accessible in their records.
            $course->topics()->whereNotIn('id', array_values($ids))->where('status', 'active')
                ->update(['status' => 'archived']);
        });
    }
}
