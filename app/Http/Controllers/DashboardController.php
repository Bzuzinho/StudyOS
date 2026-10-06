<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\ClassOccurrence;
use App\Models\Course;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\LessonSummary;
use App\Models\Material;
use App\Models\SourceChunk;
use App\Models\StudySession;
use App\Models\SyncRun;
use App\Models\Task;
use App\Models\Topic;
use App\Models\TopicMastery;
use Illuminate\View\View;

class DashboardController
{
    public function __invoke(): View
    {
        $now = now()->timezone(config('app.timezone', 'Europe/Lisbon'));
        $dayStart = $now->copy()->startOfDay();
        $dayEnd = $now->copy()->endOfDay();

        return view('dashboard', [
            'courseCount' => Course::query()->where('status', 'active')->count(),
            'plannedCourseCount' => Course::query()->where('status', 'planned')->count(),
            'activeCourses' => Course::query()
                ->where('status', 'active')
                ->withCount('classOccurrences')
                ->orderBy('name')
                ->get(),
            'todayClasses' => ClassOccurrence::query()
                ->with('course')
                ->whereBetween('starts_at', [$dayStart, $dayEnd])
                ->orderBy('starts_at')
                ->get(),
            'upcomingAssessments' => Assessment::query()
                ->with('course')
                ->whereNotNull('due_at')
                ->where('due_at', '>=', $now)
                ->where(function ($query) {
                    $query->whereNull('metadata->conditional')
                        ->orWhere('metadata->conditional', false);
                })
                ->orderBy('due_at')
                ->limit(6)
                ->get(),
            'pendingTasks' => Task::query()
                ->with('course')
                ->where('status', '!=', 'completed')
                ->orderByRaw('due_at is null, due_at asc')
                ->limit(4)
                ->get(),
            'materialCount' => Material::query()->where('status', 'active')->count(),
            'summaryCount' => LessonSummary::query()->count(),
            'recentMaterials' => Material::query()
                ->with('course')
                ->where('status', 'active')
                ->latest('updated_at')
                ->limit(4)
                ->get(),
            'topicCount' => Topic::query()->where('status', 'active')->count(),
            'upcomingStudySessions' => StudySession::query()
                ->with(['course', 'topics'])
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(4)
                ->get(),
            'plannedStudyMinutes' => StudySession::query()
                ->where('starts_at', '>=', now())
                ->sum('planned_minutes'),
            'exerciseCount' => Exercise::query()->where('status', 'active')->count(),
            'gradedAttemptCount' => ExerciseAttempt::query()->where('grading_status', 'graded')->count(),
            'masteryEvidenceCount' => TopicMastery::query()->where('status', '!=', 'no_evidence')->count(),
            'sourceChunkCount' => SourceChunk::query()->where('status', 'active')->count(),
            'richSourceChunkCount' => SourceChunk::query()
                ->where('status', 'active')
                ->where('quality', 'content')
                ->count(),
            'groundedExerciseCount' => Exercise::query()
                ->where('status', 'active')
                ->where('source', 'grounded_generator')
                ->count(),
            'recentAttempts' => ExerciseAttempt::query()
                ->with(['exercise.course'])
                ->latest('attempted_at')
                ->limit(4)
                ->get(),
            'lastSync' => SyncRun::query()->latest('started_at')->first(),
        ]);
    }
}
