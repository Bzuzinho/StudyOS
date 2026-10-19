<?php

namespace App\Services\Practice;

use DateTimeImmutable;
use DateTimeInterface;

class MasteryCalculator
{
    public const POLICY_VERSION = 'v2-weighted-multi-source';

    public function calculate(
        array $latestPercentagesByExercise,
        int $gradedAttemptCount,
        ?DateTimeInterface $lastPracticedAt,
    ): array {
        return $this->calculateCombined(
            $latestPercentagesByExercise,
            $gradedAttemptCount,
            $lastPracticedAt,
            [],
        );
    }

    public function calculateCombined(
        array $latestPercentagesByExercise,
        int $gradedAttemptCount,
        ?DateTimeInterface $lastPracticedAt,
        array $externalObservations,
    ): array {
        $localScores = array_values(array_filter(
            $latestPercentagesByExercise,
            fn ($value) => is_numeric($value),
        ));

        $external = collect($externalObservations)
            ->filter(fn ($observation) => is_array($observation)
                && is_numeric($observation['score_percent'] ?? null)
                && (int) ($observation['evidence_count'] ?? 0) > 0)
            ->map(function ($observation) {
                return [
                    'score_percent' => (float) $observation['score_percent'],
                    'evidence_count' => max(1, min(100, (int) $observation['evidence_count'])),
                    'assessed_on' => $this->dateOrNull($observation['assessed_on'] ?? null),
                ];
            })->values();

        if ($localScores === [] && $external->isEmpty()) {
            return [
                'evidence_attempts' => 0,
                'evidence_exercises' => 0,
                'evidence_units' => 0,
                'external_observations' => 0,
                'external_evidence_units' => 0,
                'score_percent' => null,
                'status' => 'no_evidence',
                'last_practiced_at' => null,
                'policy' => self::POLICY_VERSION,
            ];
        }

        $referenceDate = collect([
            $lastPracticedAt ? DateTimeImmutable::createFromInterface($lastPracticedAt) : null,
            ...$external->pluck('assessed_on')->all(),
        ])->filter()->sortDesc()->first();

        $weightedScore = 0.0;
        $weightTotal = 0.0;

        foreach ($localScores as $score) {
            $recency = $this->recencyWeight(
                $lastPracticedAt ? DateTimeImmutable::createFromInterface($lastPracticedAt) : null,
                $referenceDate,
            );
            $weightedScore += ((float) $score) * $recency;
            $weightTotal += $recency;
        }

        foreach ($external as $observation) {
            $recency = $this->recencyWeight($observation['assessed_on'], $referenceDate);
            $weight = $observation['evidence_count'] * $recency;
            $weightedScore += $observation['score_percent'] * $weight;
            $weightTotal += $weight;
        }

        $score = $weightTotal > 0 ? round($weightedScore / $weightTotal, 2) : null;
        $externalEvidenceUnits = (int) $external->sum('evidence_count');
        $evidenceUnits = count($localScores) + $externalEvidenceUnits;
        $minimumEvidence = (int) $this->setting('minimum_evidence_units', 3);
        $thresholds = $this->thresholds();

        $status = match (true) {
            $score === null => 'no_evidence',
            $evidenceUnits < $minimumEvidence => 'insufficient_evidence',
            $score < $thresholds['developing_from'] => 'fragile',
            $score < $thresholds['competent_from'] => 'developing',
            $score < $thresholds['strong_from'] => 'competent',
            default => 'strong',
        };

        $externalLast = $external->pluck('assessed_on')->filter()->sortDesc()->first();
        $last = collect([
            $lastPracticedAt ? DateTimeImmutable::createFromInterface($lastPracticedAt) : null,
            $externalLast,
        ])->filter()->sortDesc()->first();

        return [
            'evidence_attempts' => $gradedAttemptCount + $externalEvidenceUnits,
            'evidence_exercises' => count($localScores) + $external->count(),
            'evidence_units' => $evidenceUnits,
            'external_observations' => $external->count(),
            'external_evidence_units' => $externalEvidenceUnits,
            'score_percent' => $score,
            'status' => $status,
            'last_practiced_at' => $last,
            'policy' => self::POLICY_VERSION,
        ];
    }

    private function recencyWeight(?DateTimeImmutable $date, ?DateTimeImmutable $referenceDate): float
    {
        if (! $date || ! $referenceDate) {
            return 1.0;
        }

        $days = max(0, (int) $date->diff($referenceDate)->format('%a'));
        $halfLife = max(1, (int) $this->setting('recency_half_life_days', 120));
        $minimum = (float) $this->setting('minimum_recency_weight', 0.25);

        return max($minimum, pow(0.5, $days / $halfLife));
    }

    private function thresholds(): array
    {
        return [
            'developing_from' => (float) $this->setting('thresholds.developing_from', 50),
            'competent_from' => (float) $this->setting('thresholds.competent_from', 70),
            'strong_from' => (float) $this->setting('thresholds.strong_from', 85),
        ];
    }

    private function setting(string $key, mixed $default): mixed
    {
        return function_exists('config') ? config('studyos.mastery.'.$key, $default) : $default;
    }

    private function dateOrNull(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if (! is_string($value) || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
