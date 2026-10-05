<?php

namespace App\Services\Calendar;

use App\Models\Course;
use Illuminate\Support\Str;

class CourseMatcher
{
    public function match(string $title): ?Course
    {
        $needle = $this->normaliseWords($title);

        if ($needle === '') {
            return null;
        }

        $courses = Course::query()
            ->where('status', 'active')
            ->with('sourceCourses')
            ->get();

        foreach ($courses as $course) {
            if ($this->normaliseWords($course->name) === $needle) {
                return $course;
            }
        }

        foreach ($courses as $course) {
            $candidates = [$course->name];

            foreach ($course->sourceCourses as $sourceCourse) {
                $candidates[] = $sourceCourse->external_name;
                foreach (($sourceCourse->metadata['aliases'] ?? []) as $alias) {
                    $candidates[] = $alias;
                }
            }

            foreach (array_unique(array_filter($candidates)) as $candidate) {
                if ($this->matchesCandidate($needle, $candidate)) {
                    return $course;
                }
            }
        }

        return null;
    }

    private function matchesCandidate(string $needle, string $candidate): bool
    {
        $candidate = $this->normaliseWords($candidate);

        if ($candidate === '') {
            return false;
        }

        if ($needle === $candidate) {
            return true;
        }

        $pattern = '/(?:^|\s)'.preg_quote($candidate, '/').'(?:\s|$)/u';

        if (preg_match($pattern, $needle) === 1) {
            return true;
        }

        return mb_strlen(str_replace(' ', '', $candidate)) >= 6
            && str_contains(str_replace(' ', '', $needle), str_replace(' ', '', $candidate));
    }

    private function normaliseWords(string $value): string
    {
        $ascii = strtolower(Str::ascii($value));
        $normalised = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? '';

        return trim(preg_replace('/\s+/', ' ', $normalised) ?? '');
    }
}
