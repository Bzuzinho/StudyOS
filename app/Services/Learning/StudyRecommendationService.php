<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\Topic;
use Illuminate\Support\Carbon;

class StudyRecommendationService
{
    public function forCourse(Course $course): array
    {
        $course->loadMissing(['topics' => fn ($query) => $query->where('status', 'active')->with('mastery')->withCount([
            'exercises',
            'studySessions as completed_study_sessions_count' => fn ($sessions) => $sessions->where('status', 'completed'),
            'sourceChunks as rich_source_chunks_count' => fn ($chunks) => $chunks->where('source_chunks.status', 'active')->where('source_chunks.quality', 'content'),
        ])]);

        $nextAssessment = $course->assessments()->whereNotNull('due_at')
            ->where('due_at', '>=', now())
            ->where(function ($query) {
                $query->whereNull('metadata->conditional')->orWhere('metadata->conditional', false);
            })->orderBy('due_at')->first();

        $recommendations = $course->topics->filter(fn (Topic $topic) => $topic->taught_at !== null)
            ->map(function (Topic $topic) {
                $status = $topic->mastery?->status ?? 'no_evidence';
                $priority = match ($status) {
                    'fragile' => 100,
                    'no_evidence', 'insufficient_evidence' => 90,
                    'developing' => 75,
                    'competent' => 45,
                    'strong' => 15,
                    default => 90,
                };
                if ($topic->completed_study_sessions_count === 0) {
                    $priority += 5;
                }

                return [
                    'topic' => $topic,
                    'priority' => $priority,
                    'reason' => match ($status) {
                        'fragile' => 'Domínio frágil: rever erros e repetir exercícios.',
                        'insufficient_evidence' => 'Faltam exercícios distintos corrigidos para medir o domínio.',
                        'developing' => 'Consolidar conceitos e resolver exercícios adicionais.',
                        'competent' => 'Fazer uma revisão e confirmar retenção.',
                        'strong' => 'Manter revisão espaçada.',
                        default => 'Ainda sem diagnóstico: começar por exercícios sobre a matéria lecionada.',
                    },
                    'action' => $topic->rich_source_chunks_count > 0
                        ? 'practice'
                        : ($topic->exercises_count > 0 ? 'practice' : 'study'),
                    'minutes' => $priority >= 75 ? 45 : 25,
                ];
            })->sortByDesc('priority')->take(3)->values()->all();

        return [
            'course' => $course,
            'assessment' => $nextAssessment,
            'days_to_assessment' => $nextAssessment ? (int) now()->startOfDay()->diffInDays($nextAssessment->due_at->copy()->startOfDay(), false) : null,
            'recommendations' => $recommendations,
            'taught_count' => $course->topics->whereNotNull('taught_at')->count(),
            'topic_count' => $course->topics->count(),
        ];
    }
}
