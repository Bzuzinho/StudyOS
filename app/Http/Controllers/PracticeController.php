<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\StudySession;
use App\Models\Topic;
use App\Services\Practice\ExerciseGrader;
use App\Services\Practice\TopicMasteryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PracticeController
{
    public function index(Request $request): View
    {
        $exerciseQuery = Exercise::query()
            ->with(['course', 'topics', 'attempts'])
            ->where('status', 'active');

        $studySession = null;

        if ($request->integer('study_session_id')) {
            $studySession = StudySession::query()
                ->with('topics')
                ->find($request->integer('study_session_id'));

            if ($studySession) {
                $exerciseQuery->where('course_id', $studySession->course_id);

                $sessionTopicIds = $studySession->topics->pluck('id');

                if ($sessionTopicIds->isNotEmpty()) {
                    $exerciseQuery->whereHas(
                        'topics',
                        fn ($query) => $query->whereIn('topics.id', $sessionTopicIds),
                    );
                }
            }
        }

        if ($request->integer('course_id')) {
            $exerciseQuery->where('course_id', $request->integer('course_id'));
        }

        if ($request->integer('topic_id')) {
            $exerciseQuery->whereHas('topics', fn ($query) => $query->whereKey($request->integer('topic_id')));
        }

        $topics = Topic::query()
            ->with(['course', 'mastery'])
            ->withCount(['exercises', 'studySessions'])
            ->where('status', 'active')
            ->orderBy('course_id')
            ->orderBy('position')
            ->get();

        return view('practice.index', [
            'exercises' => $exerciseQuery->latest('updated_at')->get(),
            'topics' => $topics,
            'recentAttempts' => ExerciseAttempt::query()
                ->with(['exercise.course', 'exercise.topics'])
                ->latest('attempted_at')
                ->limit(8)
                ->get(),
            'courses' => Course::query()->where('status', 'active')->orderBy('name')->get(),
            'selectedCourseId' => $request->integer('course_id') ?: null,
            'selectedTopicId' => $request->integer('topic_id') ?: null,
            'studySession' => $studySession,
        ]);
    }

    public function create(Request $request): View
    {
        return view('practice.form', [
            'exercise' => new Exercise(),
            'courses' => $this->coursesWithTopics(),
            'types' => $this->types(),
            'selectedCourseId' => $request->integer('course_id') ?: null,
            'selectedTopicId' => $request->integer('topic_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedExercise($request);
        $topicIds = $this->validatedTopicIds($data['course_id'], $data['topic_ids'] ?? []);

        $exercise = DB::transaction(function () use ($data, $topicIds) {
            $exercise = Exercise::query()->create([
                'course_id' => $data['course_id'],
                'source' => 'manual',
                'external_id' => (string) Str::uuid(),
                'type' => $data['type'],
                'title' => $data['title'] ?: null,
                'prompt' => $data['prompt'],
                'expected_answer' => $data['expected_answer'] ?: null,
                'answer_config' => $this->answerConfig($data),
                'explanation' => $data['explanation'] ?: null,
                'max_points' => $data['max_points'],
                'status' => 'active',
                'metadata' => [
                    'authorship' => 'manual',
                    'source_grounded' => false,
                ],
            ]);

            $exercise->topics()->sync($topicIds);

            return $exercise;
        });

        return redirect()->route('practice.show', $exercise)
            ->with('status', 'Exercício criado. As respostas objetivas já podem ser corrigidas automaticamente.');
    }

    public function edit(Exercise $exercise): View
    {
        $this->ensureManual($exercise);
        $exercise->load('topics');

        return view('practice.form', [
            'exercise' => $exercise,
            'courses' => $this->coursesWithTopics(),
            'types' => $this->types(),
            'selectedCourseId' => $exercise->course_id,
            'selectedTopicId' => null,
        ]);
    }

    public function update(Request $request, Exercise $exercise): RedirectResponse
    {
        $this->ensureManual($exercise);
        $data = $this->validatedExercise($request);
        $topicIds = $this->validatedTopicIds($data['course_id'], $data['topic_ids'] ?? []);

        $previousTopicIds = $exercise->topics()->pluck('topics.id')->all();

        DB::transaction(function () use ($exercise, $data, $topicIds) {
            $exercise->update([
                'course_id' => $data['course_id'],
                'type' => $data['type'],
                'title' => $data['title'] ?: null,
                'prompt' => $data['prompt'],
                'expected_answer' => $data['expected_answer'] ?: null,
                'answer_config' => $this->answerConfig($data),
                'explanation' => $data['explanation'] ?: null,
                'max_points' => $data['max_points'],
            ]);

            $exercise->topics()->sync($topicIds);
        });

        $masteryService = app(TopicMasteryService::class);
        $affectedTopicIds = array_values(array_unique([...$previousTopicIds, ...$topicIds]));

        Topic::query()->whereIn('id', $affectedTopicIds)->each(
            fn (Topic $topic) => $masteryService->recalculate($topic),
        );

        return redirect()->route('practice.show', $exercise)->with('status', 'Exercício atualizado.');
    }

    public function show(Request $request, Exercise $exercise): View
    {
        $exercise->load(['course', 'topics.mastery', 'attempts']);

        $studySession = null;

        if ($request->integer('study_session_id')) {
            $studySession = StudySession::query()
                ->whereKey($request->integer('study_session_id'))
                ->where('course_id', $exercise->course_id)
                ->first();
        }

        return view('practice.show', [
            'exercise' => $exercise,
            'studySession' => $studySession,
        ]);
    }

    public function attempt(
        Request $request,
        Exercise $exercise,
        ExerciseGrader $grader,
        TopicMasteryService $masteryService,
    ): RedirectResponse {
        $data = $request->validate([
            'submitted_answer' => ['required', 'string', 'max:10000'],
            'study_session_id' => ['nullable', 'integer', Rule::exists('study_sessions', 'id')],
        ]);

        $studySession = null;

        if (! empty($data['study_session_id'])) {
            $studySession = StudySession::query()->findOrFail($data['study_session_id']);

            if ($studySession->course_id !== $exercise->course_id) {
                throw ValidationException::withMessages([
                    'study_session_id' => 'A sessão de estudo tem de pertencer à mesma UC do exercício.',
                ]);
            }
        }

        $result = $grader->grade(
            $exercise->type,
            $exercise->expected_answer,
            $exercise->answer_config ?? [],
            $data['submitted_answer'],
            (float) $exercise->max_points,
        );

        $attempt = ExerciseAttempt::query()->create([
            'exercise_id' => $exercise->id,
            'study_session_id' => $studySession?->id,
            'submitted_answer' => $data['submitted_answer'],
            'score' => $result['score'],
            'max_points' => $exercise->max_points,
            'percentage' => $result['percentage'],
            'grading_status' => $result['grading_status'],
            'grading_method' => $result['grading_method'],
            'feedback' => $result['feedback'],
            'attempted_at' => now(),
            'metadata' => [
                'exercise_type' => $exercise->type,
                'exercise_source' => $exercise->source,
            ],
        ]);

        if ($studySession) {
            $metadata = $studySession->metadata ?? [];
            $metadata['execution_evidence'] = true;
            $metadata['activity_observed_at'] = now()->toIso8601String();

            $studySession->update([
                'status' => $studySession->status === 'planned' ? 'started' : $studySession->status,
                'metadata' => $metadata,
            ]);
        }

        if ($attempt->grading_status === 'graded') {
            $masteryService->recalculateForAttempt($attempt);
        }

        return redirect()->route('practice.show', [
            'exercise' => $exercise,
            'study_session_id' => $studySession?->id,
        ])->with(
            'status',
            $attempt->grading_status === 'graded'
                ? 'Tentativa corrigida e evidência de domínio atualizada.'
                : 'Tentativa registada. A resposta aberta aguarda revisão.',
        );
    }

    public function reviewAttempt(
        Request $request,
        ExerciseAttempt $attempt,
        TopicMasteryService $masteryService,
    ): RedirectResponse {
        abort_unless($attempt->grading_status === 'pending_review', 404);

        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$attempt->max_points],
            'feedback' => ['nullable', 'string', 'max:4000'],
        ]);

        $score = (float) $data['score'];
        $maxPoints = (float) $attempt->max_points;

        $attempt->update([
            'score' => $score,
            'percentage' => $maxPoints > 0 ? round(($score / $maxPoints) * 100, 2) : 0,
            'grading_status' => 'graded',
            'grading_method' => 'manual_review',
            'feedback' => $data['feedback'] ?: null,
        ]);

        $masteryService->recalculateForAttempt($attempt->fresh());

        return redirect()->route('practice.show', $attempt->exercise_id)
            ->with('status', 'Revisão registada e domínio recalculado.');
    }

    public function destroy(Exercise $exercise): RedirectResponse
    {
        $this->ensureManual($exercise);
        $topicIds = $exercise->topics()->pluck('topics.id');

        $exercise->delete();

        $service = app(TopicMasteryService::class);

        Topic::query()->whereIn('id', $topicIds)->each(
            fn (Topic $topic) => $service->recalculate($topic),
        );

        return redirect()->route('practice.index')->with('status', 'Exercício eliminado.');
    }

    private function validatedExercise(Request $request): array
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')],
            'topic_ids' => ['required', 'array', 'min:1'],
            'topic_ids.*' => ['integer', Rule::exists('topics', 'id')],
            'type' => ['required', Rule::in(array_keys($this->types()))],
            'title' => ['nullable', 'string', 'max:255'],
            'prompt' => ['required', 'string', 'max:10000'],
            'expected_answer' => ['nullable', 'string', 'max:10000'],
            'accepted_answers' => ['nullable', 'string', 'max:10000'],
            'numeric_tolerance' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'explanation' => ['nullable', 'string', 'max:10000'],
            'max_points' => ['required', 'numeric', 'min:0.1', 'max:100'],
        ]);

        if ($data['type'] !== 'open_text' && trim((string) ($data['expected_answer'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'expected_answer' => 'Este tipo de exercício precisa de uma resposta correta para permitir correção automática.',
            ]);
        }

        if ($data['type'] === 'true_false' && ! $this->isBooleanAnswer((string) $data['expected_answer'])) {
            throw ValidationException::withMessages([
                'expected_answer' => 'Em verdadeiro/falso, usa “verdadeiro” ou “falso” como resposta correta.',
            ]);
        }

        if ($data['type'] === 'numeric' && ! $this->isNumericAnswer((string) $data['expected_answer'])) {
            throw ValidationException::withMessages([
                'expected_answer' => 'A resposta correta tem de ser numérica.',
            ]);
        }

        return $data;
    }

    private function validatedTopicIds(int $courseId, array $topicIds): array
    {
        $topicIds = array_values(array_unique(array_map('intval', $topicIds)));

        $validCount = Topic::query()
            ->whereIn('id', $topicIds)
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->count();

        if ($validCount !== count($topicIds)) {
            throw ValidationException::withMessages([
                'topic_ids' => 'Todos os tópicos escolhidos têm de pertencer à UC selecionada.',
            ]);
        }

        return $topicIds;
    }

    private function answerConfig(array $data): array
    {
        $config = [];

        if (! empty($data['accepted_answers'])) {
            $config['accepted_answers'] = array_values(array_filter(array_map(
                'trim',
                preg_split('/\R/u', $data['accepted_answers']) ?: [],
            )));
        }

        if ($data['type'] === 'numeric') {
            $config['tolerance'] = (float) ($data['numeric_tolerance'] ?? 0);
        }

        return $config;
    }

    private function coursesWithTopics()
    {
        return Course::query()
            ->where('status', 'active')
            ->with(['topics' => fn ($query) => $query->where('status', 'active')->orderBy('position')])
            ->orderBy('name')
            ->get();
    }

    private function isBooleanAnswer(string $value): bool
    {
        return in_array(
            mb_strtolower(trim($value)),
            ['true', 'false', '1', '0', 'v', 'f', 'verdadeiro', 'falso', 'sim', 'não', 'nao'],
            true,
        );
    }

    private function isNumericAnswer(string $value): bool
    {
        return is_numeric(str_replace([' ', ','], ['', '.'], trim($value)));
    }

    private function types(): array
    {
        return [
            'single_answer' => 'Resposta curta',
            'true_false' => 'Verdadeiro / falso',
            'numeric' => 'Resposta numérica',
            'open_text' => 'Resposta aberta',
        ];
    }

    private function ensureManual(Exercise $exercise): void
    {
        abort_unless($exercise->source === 'manual', 404);
    }
}
