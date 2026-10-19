<?php

namespace App\Services\Practice;

use App\Models\ExerciseAttempt;
use App\Models\Topic;
use App\Models\TopicMastery;
use Illuminate\Support\Facades\DB;

class TopicMasteryService
{
    public function __construct(private readonly MasteryCalculator $calculator) {}

    public function recalculate(Topic $topic): TopicMastery
    {
        $local = $this->localEvidence($topic);
        $external = $this->externalEvidence($topic);
        $result = $this->calculator->calculateCombined(
            $local['latest_by_exercise'],
            $local['graded_attempt_count'],
            $local['last_practiced_at'],
            $external,
        );

        return TopicMastery::query()->updateOrCreate(
            ['topic_id' => $topic->id],
            [
                'evidence_attempts' => $result['evidence_attempts'],
                'evidence_exercises' => $result['evidence_exercises'],
                'score_percent' => $result['score_percent'],
                'status' => $result['status'],
                'last_practiced_at' => $result['last_practiced_at'],
                'calculated_at' => now(),
                'metadata' => [
                    'policy' => $result['policy'],
                    'score_kind' => $result['score_percent'] === null ? 'none' : 'weighted_estimate',
                    'evidence_units' => $result['evidence_units'],
                    'local_distinct_exercises' => count($local['latest_by_exercise']),
                    'local_graded_attempts' => $local['graded_attempt_count'],
                    'external_observations' => $result['external_observations'],
                    'external_evidence_units' => $result['external_evidence_units'],
                    'latest_distinct_exercises_limit' => 8,
                    'minimum_evidence_units_for_level' => (int) config('studyos.mastery.minimum_evidence_units', 3),
                    'external_recency_half_life_days' => (int) config('studyos.mastery.recency_half_life_days', 120),
                    'thresholds' => [
                        'fragile' => [0, (float) config('studyos.mastery.thresholds.developing_from', 50) - 0.01],
                        'developing' => [(float) config('studyos.mastery.thresholds.developing_from', 50), (float) config('studyos.mastery.thresholds.competent_from', 70) - 0.01],
                        'competent' => [(float) config('studyos.mastery.thresholds.competent_from', 70), (float) config('studyos.mastery.thresholds.strong_from', 85) - 0.01],
                        'strong' => [(float) config('studyos.mastery.thresholds.strong_from', 85), 100],
                    ],
                ],
            ],
        );
    }

    public function previewExternalObservation(Topic $topic, array $candidate, string $assessedOn): array
    {
        $local = $this->localEvidence($topic);
        $external = $this->externalEvidence($topic);
        $external[] = [
            'score_percent' => $candidate['score_percent'] ?? null,
            'evidence_count' => (int) ($candidate['evidence_count'] ?? 0),
            'assessed_on' => $assessedOn,
        ];

        return $this->calculator->calculateCombined(
            $local['latest_by_exercise'],
            $local['graded_attempt_count'],
            $local['last_practiced_at'],
            $external,
        );
    }

    public function recalculateForAttempt(ExerciseAttempt $attempt): void
    {
        $attempt->exercise->loadMissing('topics');

        foreach ($attempt->exercise->topics as $topic) {
            $this->recalculate($topic);
        }
    }

    private function localEvidence(Topic $topic): array
    {
        $gradedAttempts = ExerciseAttempt::query()
            ->select('exercise_attempts.*')
            ->join('exercise_topic', 'exercise_topic.exercise_id', '=', 'exercise_attempts.exercise_id')
            ->where('exercise_topic.topic_id', $topic->id)
            ->where('exercise_attempts.grading_status', 'graded')
            ->where('exercise_attempts.grading_method', '!=', 'ai_rubric_provisional')
            ->orderByDesc('exercise_attempts.attempted_at')
            ->get();

        return [
            'latest_by_exercise' => $gradedAttempts
                ->unique('exercise_id')
                ->take(8)
                ->mapWithKeys(fn (ExerciseAttempt $attempt) => [
                    $attempt->exercise_id => (float) $attempt->percentage,
                ])->all(),
            'graded_attempt_count' => $gradedAttempts->count(),
            'last_practiced_at' => $gradedAttempts->first()?->attempted_at,
        ];
    }

    private function externalEvidence(Topic $topic): array
    {
        $number = (string) ($topic->metadata['curricular_number'] ?? '');
        if ($number === '') {
            return [];
        }

        $observations = [];
        $rows = DB::table('external_assessments')
            ->where('course_id', $topic->course_id)
            ->orderBy('assessed_on')
            ->orderBy('id')
            ->get(['assessed_on', 'report']);

        foreach ($rows as $row) {
            $report = json_decode((string) $row->report, true);
            if (! is_array($report)) {
                continue;
            }
            $schema = (string) ($report['schema'] ?? '');

            foreach (($report['topics'] ?? []) as $reportedTopic) {
                if (! is_array($reportedTopic) || (string) ($reportedTopic['number'] ?? '') !== $number) {
                    continue;
                }

                $feedback = (string) ($reportedTopic['feedback'] ?? '');
                $evidenceCount = $schema === ExternalAssessmentNormalizer::SCHEMA_V1
                    ? ($this->feedbackMeansNoEvidence($feedback) ? 0 : 1)
                    : (int) ($reportedTopic['evidence_count'] ?? 0);
                $score = $reportedTopic['score_percent'] ?? null;

                if ($evidenceCount <= 0 || ! is_numeric($score)) {
                    continue;
                }

                $observations[] = [
                    'score_percent' => (float) $score,
                    'evidence_count' => max(1, min(100, $evidenceCount)),
                    'assessed_on' => (string) $row->assessed_on,
                ];
            }
        }

        return $observations;
    }

    private function feedbackMeansNoEvidence(string $feedback): bool
    {
        return preg_match('/\b(?:sem|aus[eê]ncia de|no)\s+evid[eê]ncia\b/iu', $feedback) === 1;
    }
}
