<?php

namespace App\Http\Controllers;

use App\Models\ClassOccurrence;
use App\Models\Course;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\LessonSummary;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\SourceChunk;
use App\Models\StudySession;
use App\Models\SyncConnection;
use App\Models\SyncRun;
use App\Models\Task;
use App\Models\Topic;
use App\Models\TopicMastery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SystemStatusController
{
    public function __invoke(): JsonResponse
    {
        $lastRun = SyncRun::query()->latest('started_at')->first();
        $moodleConnection = SyncConnection::query()
            ->whereIn('source', ['moodle_manual', 'moodle_webservice', 'moodle_authenticated'])
            ->orderByRaw("case when source = 'moodle_manual' then 0 when source = 'moodle_webservice' then 1 else 2 end")
            ->first();
        $moodleRun = $moodleConnection?->runs()->latest('started_at')->first();

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
                'uploaded_files' => MaterialVersion::query()->whereNotNull('storage_path')->count(),
                'files_queued' => MaterialVersion::query()->where('extraction_status', 'queued')->count(),
                'files_processing' => MaterialVersion::query()->where('extraction_status', 'processing')->count(),
                'files_extracted' => MaterialVersion::query()->where('extraction_status', 'extracted')->count(),
                'files_without_searchable_text' => MaterialVersion::query()
                    ->whereIn('extraction_status', ['empty', 'empty_or_scanned'])
                    ->count(),
                'file_extraction_failures' => MaterialVersion::query()->where('extraction_status', 'failed')->count(),
                'lesson_summaries' => LessonSummary::count(),
                'open_tasks' => Task::query()->where('status', '!=', 'completed')->count(),
                'topics' => Topic::query()->where('status', 'active')->count(),
                'study_sessions_planned' => StudySession::count(),
                'study_minutes_planned' => StudySession::sum('planned_minutes'),
                'exercises' => Exercise::query()->where('status', 'active')->count(),
                'attempts' => ExerciseAttempt::count(),
                'graded_attempts' => ExerciseAttempt::query()->where('grading_status', 'graded')->count(),
                'topics_with_mastery_evidence' => TopicMastery::query()->where('status', '!=', 'no_evidence')->count(),
                'source_chunks_active' => SourceChunk::query()->where('status', 'active')->count(),
                'source_chunks_rich' => SourceChunk::query()
                    ->where('status', 'active')
                    ->where('quality', 'content')
                    ->count(),
                'grounded_exercises' => Exercise::query()
                    ->where('status', 'active')
                    ->where('source', 'grounded_generator')
                    ->count(),
            ],
            'background_queue' => [
                'pending_jobs' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ],
            'moodle_sync' => [
                'configured' => $moodleConnection
                    && ! in_array($moodleConnection->status, ['pending_credentials', 'pending_microsoft_sso'], true),
                'status' => $moodleConnection?->status ?? 'not_configured',
                'last_synced_at' => $moodleConnection?->last_synced_at?->toIso8601String(),
                'last_run' => $moodleRun ? [
                    'status' => $moodleRun->status,
                    'started_at' => $moodleRun->started_at?->toIso8601String(),
                    'finished_at' => $moodleRun->finished_at?->toIso8601String(),
                    'stats' => $moodleRun->stats,
                    'error' => $moodleRun->status === 'failed' ? $moodleRun->error : null,
                ] : null,
                'materials' => Material::query()->where('source', 'moodle')->count(),
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
