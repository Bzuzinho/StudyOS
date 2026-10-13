<?php
namespace App\Services\Practice;
use App\Models\Course;
use App\Models\Exercise;
use App\Services\Learning\Curriculum;
use Illuminate\Database\Eloquent\Builder;
class PracticeQuestionPool
{
    public function query(Course $course, array $topicIds, array $filters): Builder
    {
        $leaves = Curriculum::expand($course, $topicIds);
        $query = Exercise::query()->where('course_id', $course->id)->where('status', 'active')
            ->whereHas('topics', fn ($q) => $q->whereIn('topics.id', $leaves));
        if (!empty($filters['difficulty'])) {
            $query->whereIn('metadata->difficulty', match ($filters['difficulty']) {
                'basic' => ['basico', 'básico', 'facil', 'fácil', 'basic', 'easy'],
                'intermediate' => ['medio', 'médio', 'intermedio', 'intermédio', 'intermediate', 'medium'],
                'advanced' => ['avancado', 'avançado', 'dificil', 'difícil', 'advanced', 'hard'],
                default => [],
            });
        }
        if (!empty($filters['types'])) $query->whereIn('type', $filters['types']);
        if (!empty($filters['errors_only'])) {
            $query->whereHas('attempts', fn ($q) => $q->where('grading_status', 'graded')->whereColumn('score', '<', 'max_points'));
        }
        if (!empty($filters['repeat_run_id'])) {
            $run = \Illuminate\Support\Facades\DB::table('practice_runs')->where('course_id', $course->id)->find($filters['repeat_run_id']);
            abort_unless($run, 404);
            $wrong = collect(json_decode($run->responses, true) ?: [])->filter(fn ($r) => ($r['grading_status'] ?? '') === 'graded' && ($r['score'] ?? 0) < ($r['max_points'] ?? 1))->keys()->all();
            $query->whereIn('id', $wrong);
        }
        return $query;
    }
}
