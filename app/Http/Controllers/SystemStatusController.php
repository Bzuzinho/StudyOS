<?php

namespace App\Http\Controllers;

use App\Models\ClassOccurrence;
use App\Models\Course;
use App\Models\LessonSummary;
use App\Models\Material;
use App\Models\StudySession;
use App\Models\SyncConnection;
use App\Models\SyncRun;
use App\Models\Task;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;

class SystemStatusController
{
    public function __invoke(): JsonResponse
    {
        $lastRun = SyncRun::query()->latest('started_at')->first();

        return response()->json([
            'courses' => [
                'total' => Course::count(),
                'active' => Course::query()->where('status', 'active')->count(),
                'planned' => Course::query()->where('status', 'planned')->count(),
            ],
            'calendar_events' => [
                'total' => ClassOccurrence::count(),
                'linked_to_course' => ClassOccurrence::query()->whereNotNull('course_id')->count(),
                'unlinked' => ClassOccurrence::query()->whereNull('course_id')->count(),
            ],
            'learning_context' => [
                'materials' => Material::query()->where('status', 'active')->count(),
                'lesson_summaries' => LessonSummary::count(),
                'open_tasks' => Task::query()->where('status', '!=', 'completed')->count(),
                'topics' => Topic::query()->where('status', 'active')->count(),
                'study_sessions_planned' => StudySession::count(),
                'study_minutes_planned' => StudySession::sum('planned_minutes'),
            ],
            'sync_connections' => SyncConnection::count(),
            'last_sync' => $lastRun ? [
                'status' => $lastRun->status,
                'started_at' => $lastRun->started_at?->toIso8601String(),
                'finished_at' => $lastRun->finished_at?->toIso8601String(),
                'stats' => $lastRun->stats,
                'error' => $lastRun->status === 'failed' ? $lastRun->error : null,
            ] : null,
        ]);
    }
}
