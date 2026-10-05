<?php

namespace App\Services\Calendar;

use App\Models\Course;
use Illuminate\Support\Str;

class CourseMatcher
{
    public function match(string $title): ?Course
    {
        $needle = $this->normalise($title);

        if ($needle === '') {
            return null;
        }

        $courses = Course::query()->where('status', 'active')->get();

        foreach ($courses as $course) {
            if ($this->normalise($course->name) === $needle) {
                return $course;
            }
        }

        foreach ($courses as $course) {
            $name = $this->normalise($course->name);

            if (mb_strlen($name) >= 6 && (str_contains($needle, $name) || str_contains($name, $needle))) {
                return $course;
            }
        }

        return null;
    }

    private function normalise(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(Str::ascii($value))) ?? '';
    }
}
