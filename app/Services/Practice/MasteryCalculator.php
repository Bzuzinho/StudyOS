<?php

namespace App\Services\Practice;

use DateTimeInterface;

class MasteryCalculator
{
    public const POLICY_VERSION = 'v1-latest-distinct-exercises';

    public function calculate(
        array $latestPercentagesByExercise,
        int $gradedAttemptCount,
        ?DateTimeInterface $lastPracticedAt,
    ): array {
        $percentages = array_values(array_filter(
            $latestPercentagesByExercise,
            fn ($value) => is_numeric($value),
        ));

        $exerciseCount = count($percentages);

        if ($exerciseCount === 0) {
            return [
                'evidence_attempts' => 0,
                'evidence_exercises' => 0,
                'score_percent' => null,
                'status' => 'no_evidence',
                'last_practiced_at' => null,
                'policy' => self::POLICY_VERSION,
            ];
        }

        $score = round(array_sum($percentages) / $exerciseCount, 2);

        $status = match (true) {
            $exerciseCount < 3 => 'insufficient_evidence',
            $score < 50 => 'fragile',
            $score < 70 => 'developing',
            $score < 85 => 'competent',
            default => 'strong',
        };

        return [
            'evidence_attempts' => $gradedAttemptCount,
            'evidence_exercises' => $exerciseCount,
            'score_percent' => $score,
            'status' => $status,
            'last_practiced_at' => $lastPracticedAt,
            'policy' => self::POLICY_VERSION,
        ];
    }
}
