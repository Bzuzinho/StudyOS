<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\View\View;

class CourseController
{
    public function index(): View
    {
        $courses = Course::query()
            ->withCount(['classOccurrences', 'assessments', 'tasks', 'materials', 'lessonSummaries', 'topics', 'studySessions', 'exercises', 'sourceChunks'])
            ->with(['classOccurrences' => fn ($query) => $query
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(1)])
            ->orderBy('semester')
            ->orderBy('name')
            ->get()
            ->groupBy('semester');

        return view('courses.index', [
            'coursesBySemester' => $courses,
            'activeCount' => Course::query()->where('status', 'active')->count(),
            'plannedCount' => Course::query()->where('status', 'planned')->count(),
        ]);
    }

    public function show(Course $course): View
    {
        $course->load([
            'sourceCourses',
            'classOccurrences' => fn ($query) => $query->orderBy('starts_at'),
            'assessments' => fn ($query) => $query->orderBy('due_at'),
            'tasks' => fn ($query) => $query->orderByRaw('due_at is null, due_at asc'),
            'materials' => fn ($query) => $query->with('versions')->where('status', 'active')->orderByDesc('updated_at'),
            'lessonSummaries' => fn ($query) => $query->orderByDesc('occurred_at'),
            'topics' => fn ($query) => $query
                ->where('status', 'active')
                ->with('mastery')
                ->withCount([
                    'studySessions',
                    'exercises',
                    'sourceChunks',
                    'sourceChunks as rich_source_chunks_count' => fn ($sourceQuery) => $sourceQuery
                        ->where('source_chunks.status', 'active')
                        ->where('source_chunks.quality', 'content'),
                ])
                ->orderBy('position'),
            'studySessions' => fn ($query) => $query->with('topics')->orderBy('starts_at'),
            'exercises' => fn ($query) => $query
                ->where('status', 'active')
                ->with(['sourceChunks' => fn ($sourceQuery) => $sourceQuery->where('source_chunks.status', 'active')])
                ->withCount('attempts')
                ->latest('updated_at'),
            'sourceChunks' => fn ($query) => $query
                ->where('status', 'active')
                ->with(['materialVersion.material', 'lessonSummary'])
                ->orderBy('title')
                ->orderBy('ordinal'),
        ]);

        $now = now();
        $upcomingClasses = $course->classOccurrences
            ->filter(fn ($event) => $event->starts_at->gte($now))
            ->take(12);
        $recentClasses = $course->classOccurrences
            ->filter(fn ($event) => $event->starts_at->lt($now))
            ->sortByDesc('starts_at')
            ->take(8);

        return view('courses.show', [
            'course' => $course,
            'upcomingClasses' => $upcomingClasses,
            'recentClasses' => $recentClasses,
        ]);
    }
}
