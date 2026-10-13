<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\View\View;

class CourseController
{
    public function index(): View
    {
        $courses = Course::query()
            ->withCount([
                'classOccurrences as scheduled_classes_count' => fn ($query) => $query->scheduledClasses(),
                'classOccurrences as elapsed_classes_count' => fn ($query) => $query->elapsedClasses(),
                'assessments',
                'tasks',
                'materials',
                'lessonSummaries',
                'topics' => fn ($query) => $query->where('status', 'active'),
                'topics as taught_topics_count' => fn ($query) => $query->where('status', 'active')->whereNotNull('taught_at'),
                'studySessions',
                'exercises',
                'exercises as graded_exercises_count' => fn ($query) => $query->whereHas('attempts', fn ($attempts) => $attempts->where('grading_status', 'graded')),
                'materials as downloaded_materials_count' => fn ($query) => $query->whereHas('versions', fn ($versions) => $versions->whereNotNull('storage_path')),
                'materials as extracted_materials_count' => fn ($query) => $query->whereHas('versions', fn ($versions) => $versions->where('extraction_status', 'extracted')),
                'materials as failed_materials_count' => fn ($query) => $query->whereHas('versions', fn ($versions) => $versions->where('extraction_status', 'failed')),
                'topics as practiced_topics_count' => fn ($query) => $query->where('status', 'active')->whereHas('mastery', fn ($mastery) => $mastery->whereNotIn('status', ['no_evidence', 'insufficient_evidence'])),
                'sourceChunks as source_chunks_count' => fn ($query) => $query->where('status', 'active'),
            ])
            ->with(['topics' => fn ($q) => $q->where('status', 'active')->with('mastery'), 'classOccurrences' => fn ($query) => $query
                ->scheduledClasses()
                ->where('starts_at', '>=', now()->utc())
                ->orderBy('starts_at')
                ->limit(1)])
            ->orderBy('semester')
            ->orderBy('name')
            ->get()
            ->each(function ($course) {
                $leaves = \App\Services\Learning\Curriculum::leaves($course);
                $course->topics_count = $leaves->count();
                $course->taught_topics_count = $leaves->whereNotNull('taught_at')->count();
                $course->practiced_topics_count = $leaves->filter(fn ($t) => in_array($t->mastery?->status, ['competent', 'strong']))->count();
            })
            ->groupBy('semester');

        return view('courses.index', [
            'coursesBySemester' => $courses,
            'activeCount' => Course::query()->where('status', 'active')->count(),
            'plannedCount' => Course::query()->where('status', 'planned')->count(),
        ]);
    }

    public function show(Course $course): View
    {
        $course->loadCount([
            'classOccurrences as scheduled_classes_count' => fn ($q) => $q->scheduledClasses(),
            'classOccurrences as elapsed_classes_count' => fn ($q) => $q->elapsedClasses(),
        ]);
        $course->load([
            'topics' => fn ($q) => $q->where('status', 'active')->with('mastery')
                ->withCount(['studySessions as completed_study_sessions_count' => fn ($s) => $s->where('status', 'completed')])->orderBy('position'),
            'materials' => fn ($q) => $q->where('status', 'active')->with('versions'),
        ]);
        $curriculum = \App\Services\Learning\Curriculum::topics($course);
        $leaves = \App\Services\Learning\Curriculum::leaves($course);
        $nextClass = $course->classOccurrences()->scheduledClasses()->where('starts_at', '>=', now())->orderBy('starts_at')->first();
        $nextAssessment = $course->assessments()->where('due_at', '>=', now())
            ->where(fn ($q) => $q->whereNull('metadata->conditional')->orWhere('metadata->conditional', false))
            ->orderBy('due_at')->first();
        $nextSession = $course->studySessions()->whereIn('status', ['planned', 'started'])
            ->where(fn ($q) => $q->where('status', 'started')->orWhere('starts_at', '>=', now()))
            ->orderByRaw("case when status = 'started' then 0 else 1 end")->orderBy('starts_at')->first();
        $priority = $leaves->filter(fn ($t) => $t->taught_at && in_array($t->mastery?->status, ['fragile', 'developing']))
            ->sortBy(fn ($t) => $t->mastery->score_percent)->first();
        $availableMaterials = $course->materials->filter(fn ($m) => $m->versions->contains(fn ($v) => $v->storage_path || trim((string) $v->content_text) !== ''));
        $manualTopicIds = $leaves->filter(fn ($t) => $availableMaterials->contains(fn ($m) => $m->source === 'chatgpt_package' && $m->external_id === $t->external_id))->pluck('id');
        return view('courses.show', compact('course', 'curriculum', 'leaves', 'nextClass', 'nextAssessment', 'nextSession', 'priority', 'availableMaterials', 'manualTopicIds'));
    }
}
