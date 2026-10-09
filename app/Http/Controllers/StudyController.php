<?php

namespace App\Http\Controllers;

use App\Models\ClassOccurrence;
use App\Models\Course;
use App\Models\StudySession;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudyController
{
    public function index(): View
    {
        $courses = Course::query()
            ->where('status', 'active')
            ->with([
                'topics' => fn ($query) => $query
                    ->where('status', 'active')
                    ->with('mastery')
                    ->withCount([
                        'studySessions',
                        'studySessions as completed_study_sessions_count' => fn ($sessions) => $sessions->where('status', 'completed'),
                        'exercises',
                        'sourceChunks as source_chunks_count' => fn ($sourceQuery) => $sourceQuery
                            ->where('source_chunks.status', 'active'),
                        'sourceChunks as rich_source_chunks_count' => fn ($sourceQuery) => $sourceQuery
                            ->where('source_chunks.status', 'active')
                            ->where('source_chunks.quality', 'content'),
                    ])
                    ->orderBy('position'),
            ])
            ->orderBy('name')
            ->get();

        $sessions = StudySession::query()
            ->with(['course', 'topics'])
            ->orderBy('starts_at')
            ->get();

        return view('study.index', [
            'courses' => $courses,
            'sessions' => $sessions,
            'topicCount' => Topic::query()->where('status', 'active')->count(),
            'plannedMinutes' => StudySession::query()->where('status', '!=', 'completed')->sum('planned_minutes'),
            'completedMinutes' => StudySession::query()->where('status', 'completed')->get()->sum(fn ($session) => (int) ($session->metadata['actual_minutes'] ?? 0)),
        ]);
    }

    public function create(Request $request): View
    {
        $courses = Course::query()
            ->where('status', 'active')
            ->with(['topics' => fn ($query) => $query->where('status', 'active')->orderBy('position')])
            ->orderBy('name')
            ->get();

        return view('study.form', [
            'courses' => $courses,
            'selectedCourseId' => $request->integer('course_id') ?: null,
            'selectedDate' => $request->string('date')->toString() ?: now()->timezone(config('app.timezone'))->toDateString(),
            'types' => $this->types(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')],
            'topic_ids' => ['nullable', 'array'],
            'topic_ids.*' => ['integer', Rule::exists('topics', 'id')],
            'type' => ['required', Rule::in(array_keys($this->types()))],
            'title' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'planned_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $topicIds = array_values(array_unique($data['topic_ids'] ?? []));

        if ($topicIds !== []) {
            $validTopicCount = Topic::query()
                ->whereIn('id', $topicIds)
                ->where('course_id', $data['course_id'])
                ->where('status', 'active')
                ->count();

            if ($validTopicCount !== count($topicIds)) {
                throw ValidationException::withMessages([
                    'topic_ids' => 'Todos os tópicos escolhidos têm de pertencer à UC selecionada.',
                ]);
            }
        }

        $timezone = config('app.timezone', 'Europe/Lisbon');
        $startsAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $data['date'].' '.$data['start_time'],
            $timezone,
        )->utc();
        $externalId = (string) Str::uuid();

        DB::transaction(function () use ($data, $topicIds, $startsAt, $externalId) {
            $course = Course::query()->findOrFail($data['course_id']);

            $session = StudySession::query()->create([
                'course_id' => $course->id,
                'source' => 'manual',
                'external_id' => $externalId,
                'type' => $data['type'],
                'title' => ($data['title'] ?? null) ?: null,
                'starts_at' => $startsAt,
                'planned_minutes' => $data['planned_minutes'],
                'status' => 'planned',
                'notes' => ($data['notes'] ?? null) ?: null,
                'metadata' => [
                    'execution_confirmed' => false,
                    'created_in_studyos' => true,
                ],
            ]);

            $session->topics()->sync($topicIds);

            ClassOccurrence::query()->updateOrCreate(
                ['source' => 'study_plan', 'external_uid' => $externalId],
                [
                    'course_id' => $course->id,
                    'title' => $session->title ?: 'Estudo · '.$course->name,
                    'location' => null,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addMinutes($session->planned_minutes),
                    'status' => 'scheduled',
                    'last_seen_at' => now(),
                    'source_payload' => [
                        'event_type' => 'study',
                        'study_session_id' => $session->id,
                        'planned_minutes' => $session->planned_minutes,
                        'execution_confirmed' => false,
                    ],
                ],
            );
        });

        return redirect()->route('study.index')->with('status', 'Sessão de estudo planeada.');
    }

    public function complete(Request $request, StudySession $studySession): RedirectResponse
    {
        abort_unless($studySession->source === 'manual', 404);

        $data = $request->validate([
            'actual_minutes' => ['required', 'integer', 'min:1', 'max:480'],
        ]);

        $metadata = $studySession->metadata ?? [];
        $metadata['execution_confirmed'] = true;
        $metadata['completed_at'] = now()->toIso8601String();
        $metadata['actual_minutes'] = (int) $data['actual_minutes'];

        $studySession->update([
            'status' => 'completed',
            'metadata' => $metadata,
        ]);

        return redirect()->route('study.index')->with('status', 'Estudo realizado registado com sucesso.');
    }

    public function destroy(StudySession $studySession): RedirectResponse
    {
        abort_unless($studySession->source === 'manual', 404);

        DB::transaction(function () use ($studySession) {
            ClassOccurrence::query()
                ->where('source', 'study_plan')
                ->where('external_uid', $studySession->external_id)
                ->delete();

            $studySession->delete();
        });

        return redirect()->route('study.index')->with('status', 'Sessão planeada eliminada.');
    }

    private function types(): array
    {
        return [
            'study' => 'Estudo',
            'review' => 'Revisão',
            'practice' => 'Exercícios / prática',
            'reading' => 'Leitura',
            'assignment' => 'Preparação de trabalho',
        ];
    }
}
