<?php

namespace App\Services\Learning;

use App\Models\Course;
use Illuminate\Support\Facades\DB;

class KnowledgeDashboardService
{
    public function overview(): array
    {
        $courses = Course::query()->where('status', 'active')
            ->with(['topics' => fn ($query) => $query->where('status', 'active')->with('mastery')])
            ->orderBy('name')->get();

        $rows = $courses->map(fn (Course $course) => $this->forCourse($course));
        $total = $rows->sum('total');
        $taught = $rows->sum('taught');
        $assessed = $rows->sum('assessed');
        $mastered = $rows->sum('mastered');

        return [
            'courses' => $rows,
            'total' => $total,
            'taught' => $taught,
            'assessed' => $assessed,
            'mastered' => $mastered,
            'coverage_percent' => $total ? round(100 * $taught / $total) : 0,
            'evidence_percent' => $total ? round(100 * $assessed / $total) : 0,
            'mastery_percent' => $total ? round(100 * $mastered / $total) : 0,
            'priorities' => $rows->flatMap(fn ($row) => $row['priorities'])->sortByDesc('priority')->take(6)->values(),
        ];
    }

    public function forCourse(Course $course): array
    {
        $course->loadMissing(['topics' => fn ($query) => $query->where('status', 'active')->with('mastery')]);
        $leaves = Curriculum::leaves($course);
        $reports = DB::table('external_assessments')->where('course_id', $course->id)
            ->orderByDesc('assessed_on')->orderByDesc('id')->limit(30)->get();
        $latestReport = $reports->first();
        $latest = $latestReport ? json_decode((string) $latestReport->report, true) : null;
        $latest = is_array($latest) ? $latest : [];
        $importedTopics = collect($reports)->flatMap(function ($row) {
            $report = json_decode((string) $row->report, true);
            return is_array($report) ? ($report['topics'] ?? []) : [];
        })->filter('is_array')->values();

        $priorities = $leaves->map(function ($topic) {
            $status = $topic->mastery?->status ?? 'no_evidence';
            $taught = $topic->taught_at !== null;
            $level = match ($status) {
                'fragile' => 100,
                'developing' => 85,
                'insufficient_evidence' => 75,
                'no_evidence' => 65,
                'competent' => 30,
                'strong' => 10,
                default => 65,
            };
            return [
                'course_id' => $topic->course_id,
                'topic_id' => $topic->id,
                'number' => (string) ($topic->metadata['curricular_number'] ?? ''),
                'title' => $topic->title,
                'status' => $status,
                'score' => $topic->mastery?->score_percent,
                'evidence_units' => (int) ($topic->mastery?->metadata['evidence_units'] ?? $topic->mastery?->evidence_exercises ?? 0),
                'priority' => $taught ? $level + 10 : $level - 35,
                'taught' => $taught,
                'reason' => match ($status) {
                    'fragile' => 'Rever os erros e voltar a praticar',
                    'developing' => 'Consolidar conhecimentos com exercícios',
                    'insufficient_evidence' => 'Obter mais evidência avaliativa',
                    'no_evidence' => 'Realizar um diagnóstico de conhecimento',
                    'competent' => 'Confirmar retenção numa revisão',
                    'strong' => 'Manter revisão espaçada',
                    default => 'Avaliar conhecimento',
                },
            ];
        })->sortByDesc('priority')->values();

        $assessed = $leaves->filter(fn ($topic) =>
            ! in_array($topic->mastery?->status ?? 'no_evidence', ['no_evidence', 'insufficient_evidence'], true))->count();
        $withEvidence = $leaves->filter(fn ($topic) => ($topic->mastery?->status ?? 'no_evidence') !== 'no_evidence')->count();

        return [
            'course' => $course,
            'total' => $leaves->count(),
            'taught' => $leaves->whereNotNull('taught_at')->count(),
            'with_evidence' => $withEvidence,
            'assessed' => $assessed,
            'mastered' => $leaves->filter(fn ($topic) => in_array($topic->mastery?->status, ['competent', 'strong'], true))->count(),
            'status_counts' => collect(['no_evidence','insufficient_evidence','fragile','developing','competent','strong'])
                ->mapWithKeys(fn ($status) => [$status => $leaves->filter(fn ($topic) => ($topic->mastery?->status ?? 'no_evidence') === $status)->count()])->all(),
            'last_assessed_on' => $latestReport?->assessed_on,
            'imported_topics_count' => $importedTopics->count(),
            'imported_topics' => $importedTopics->take(20)->values()->all(),
            'last_score_20' => $latestReport?->score_20,
            'report_count' => DB::table('external_assessments')->where('course_id', $course->id)->count(),
            'difficulties' => array_slice(array_values(array_filter($latest['difficulties'] ?? [], 'is_string')), 0, 4),
            'conceptual_errors' => array_slice(array_values(array_filter($latest['conceptual_errors'] ?? [], 'is_string')), 0, 4),
            'recommendations' => array_slice(array_values(array_filter($latest['recommendations'] ?? ($latest['next_steps'] ?? []), 'is_string')), 0, 4),
            'priorities' => $priorities->take(3)->values(),
            'topics' => $priorities,
        ];
    }
}
