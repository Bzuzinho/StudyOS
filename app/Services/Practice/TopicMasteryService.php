<?php

namespace App\Services\Practice;

use App\Models\ExerciseAttempt;
use App\Models\Topic;
use App\Models\TopicMastery;

class TopicMasteryService
{
    public function __construct(private readonly MasteryCalculator $calculator) {}

    public function recalculate(Topic $topic): TopicMastery
    {
        $gradedAttempts = ExerciseAttempt::query()
            ->select('exercise_attempts.*')
            ->join('exercise_topic', 'exercise_topic.exercise_id', '=', 'exercise_attempts.exercise_id')
            ->where('exercise_topic.topic_id', $topic->id)
            ->where('exercise_attempts.grading_status', 'graded')
            ->orderByDesc('exercise_attempts.attempted_at')
            ->get();

        $latestByExercise = $gradedAttempts
            ->unique('exercise_id')
            ->take(8)
            ->mapWithKeys(fn (ExerciseAttempt $attempt) => [
                $attempt->exercise_id => (float) $attempt->percentage,
            ])
            ->all();

        $lastPracticed = $gradedAttempts->first()?->attempted_at;

        $result = $this->calculator->calculate(
            $latestByExercise,
            $gradedAttempts->count(),
            $lastPracticed,
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
                    'latest_distinct_exercises_limit' => 8,
                    'minimum_distinct_exercises_for_level' => 3,
                    'thresholds' => [
                        'fragile_below' => 50,
                        'developing_below' => 70,
                        'competent_below' => 85,
                        'strong_from' => 85,
                    ],
                ],
            ],
        );
    }

    public function recalculateForAttempt(ExerciseAttempt $attempt): void
    {
        $attempt->exercise->loadMissing('topics');

        foreach ($attempt->exercise->topics as $topic) {
            $this->recalculate($topic);
        }
    }
}
