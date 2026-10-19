<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\StudySession;
use App\Services\Learning\Curriculum;
use App\Services\Practice\ExternalAssessmentNormalizer;
use App\Services\Practice\TopicMasteryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExternalAssessmentController
{
    public function index(Request $request, Course $course)
    {
        return view('practice.external', $this->viewData($request, $course));
    }

    public function preview(
        Request $request,
        Course $course,
        ExternalAssessmentNormalizer $normalizer,
        TopicMasteryService $mastery,
    ) {
        $data = $request->validate(['report' => ['required', 'string', 'max:100000']]);
        $preview = $normalizer->normalize($course, $data['report']);

        if ($preview['ok']) {
            $topics = Curriculum::leaves($course->loadMissing('topics'))
                ->keyBy(fn ($topic) => (string) ($topic->metadata['curricular_number'] ?? ''));

            $preview['report']['topics'] = collect($preview['report']['topics'])->map(function ($topic) use ($topics, $mastery, $preview) {
                $model = $topics->get((string) $topic['number']);
                $proposed = $mastery->previewExternalObservation($model, $topic, $preview['report']['assessed_on']);

                return [
                    ...$topic,
                    'title' => $model->title,
                    'proposed_status' => $proposed['status'],
                    'proposed_score_percent' => $proposed['score_percent'],
                    'proposed_evidence_units' => $proposed['evidence_units'],
                ];
            })->values()->all();
        }

        return view('practice.external', [
            ...$this->viewData($request, $course),
            'preview' => $preview,
            'rawReport' => $data['report'],
        ]);
    }

    public function confirm(
        Request $request,
        Course $course,
        ExternalAssessmentNormalizer $normalizer,
        TopicMasteryService $mastery,
    ) {
        $data = $request->validate(['normalized_report' => ['required', 'string', 'max:100000']]);
        $normalized = $normalizer->normalize($course, $data['normalized_report']);
        if (! $normalized['ok']) {
            throw ValidationException::withMessages([
                'report' => 'O relatório normalizado deixou de ser válido. Volta a analisá-lo antes de importar.',
            ]);
        }

        $this->import($course, $normalized['report'], $mastery);

        return redirect()->route('practice.external.index', $course)
            ->with('status', 'Relatório importado e estado de conhecimento atualizado. As notas oficiais não foram alteradas.');
    }

    /**
     * Mantém compatibilidade com o endpoint introduzido na v1. A interface atual usa preview + confirmação.
     */
    public function store(
        Request $request,
        Course $course,
        ExternalAssessmentNormalizer $normalizer,
        TopicMasteryService $mastery,
    ) {
        $data = $request->validate(['report' => ['required', 'string', 'max:100000']]);
        $normalized = $normalizer->normalize($course, $data['report']);
        if (! $normalized['ok']) {
            throw ValidationException::withMessages([
                'report' => 'Relatório incompatível ou incompleto: '.implode(', ', $normalized['missing_fields']),
            ]);
        }

        $this->import($course, $normalized['report'], $mastery);

        return redirect()->route('practice.external.index', $course)
            ->with('status', 'Avaliação externa importada e conhecimento atualizado. As notas oficiais não foram alteradas.');
    }

    private function import(Course $course, array $report, TopicMasteryService $mastery): void
    {
        if (DB::table('external_assessments')
            ->where('course_id', $course->id)
            ->where('external_id', $report['assessment_id'])
            ->exists()) {
            throw ValidationException::withMessages([
                'report' => 'Este relatório já foi importado. O identificador da avaliação tem de ser único.',
            ]);
        }

        $topics = Curriculum::leaves($course->loadMissing('topics'))
            ->keyBy(fn ($topic) => (string) ($topic->metadata['curricular_number'] ?? ''));

        DB::transaction(function () use ($course, $report, $mastery, $topics) {
            DB::table('external_assessments')->insert([
                'course_id' => $course->id,
                'external_id' => $report['assessment_id'],
                'assessed_on' => $report['assessed_on'],
                'score_20' => $report['score_20'],
                'report' => json_encode($report, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($report['topics'] as $topic) {
                $mastery->recalculate($topics->get((string) $topic['number']));
            }
        });
    }

    private function viewData(Request $request, Course $course): array
    {
        $topics = Curriculum::leaves($course->load('topics'));
        $selectedIds = collect();

        if ($request->integer('study_session_id')) {
            $session = StudySession::with('topics')
                ->where('course_id', $course->id)
                ->find($request->integer('study_session_id'));
            if ($session) {
                $selectedIds = collect(Curriculum::expand($course, $session->topics->pluck('id')->all()));
            }
        } elseif ($request->integer('topic_id')) {
            $selectedIds = collect(Curriculum::expand($course, [$request->integer('topic_id')]));
        }

        $selectedTopicNumbers = $topics->whereIn('id', $selectedIds)
            ->map(fn ($topic) => (string) ($topic->metadata['curricular_number'] ?? ''))
            ->filter()->values()->all();
        $history = DB::table('external_assessments')
            ->where('course_id', $course->id)
            ->latest('assessed_on')
            ->latest('id')
            ->limit(20)
            ->get();
        $projectUrl = DB::table('course_chatgpt_projects')
            ->where('course_id', $course->id)
            ->value('project_url');

        return compact('course', 'topics', 'history', 'selectedTopicNumbers', 'projectUrl');
    }
}
