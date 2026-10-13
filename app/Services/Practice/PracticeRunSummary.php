<?php
namespace App\Services\Practice;
use App\Models\ExerciseAttempt;
use Illuminate\Support\Collection;
class PracticeRunSummary
{
    public function forRuns(Collection $runs): array
    {
        $attempts = ExerciseAttempt::whereIn('metadata->practice_run_id', $runs->pluck('id'))->get()
            ->groupBy(fn ($a) => $a->metadata['practice_run_id']);
        return $runs->mapWithKeys(function ($run) use ($attempts) {
            $responses = collect(json_decode($run->responses, true) ?: []);
            foreach ($attempts->get($run->id, collect()) as $attempt) {
                $responses->put($attempt->exercise_id, ['score' => $attempt->score, 'max_points' => $attempt->max_points,
                    'grading_status' => $attempt->grading_status, 'grading_method' => $attempt->grading_method]);
            }
            $total = count(json_decode($run->exercise_ids, true) ?: []);
            $graded = $responses->where('grading_status', 'graded');
            $pending = max(0, $total - $graded->count());
            $maximum = $graded->sum(fn ($r) => (float) ($r['max_points'] ?? 0));
            $score = $run->status === 'completed' && $pending === 0 && $maximum > 0
                ? round($graded->sum(fn ($r) => (float) ($r['score'] ?? 0)) / $maximum * 20, 1) : null;
            return [$run->id => ['score' => $score, 'pending' => $pending,
                'provisional' => $graded->contains(fn ($r) => ($r['grading_method'] ?? '') === 'ai_rubric_provisional')]];
        })->all();
    }
}
