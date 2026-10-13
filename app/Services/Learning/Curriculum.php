<?php
namespace App\Services\Learning;
use App\Models\Course;
use Illuminate\Support\Collection;
class Curriculum
{
    public static function topics(Course $course): Collection
    {
        $topics = $course->topics->where('status', 'active');
        $package = $topics->where('source', 'chatgpt_package');
        return ($package->isNotEmpty() ? $package : $topics)->sortBy('position')->values();
    }
    public static function leaves(Course $course): Collection
    {
        $topics = self::topics($course);
        $parents = $topics->pluck('parent_id')->filter()->all();
        return $topics->reject(fn ($topic) => in_array($topic->id, $parents))->values();
    }
    public static function expand(Course $course, array $ids): array
    {
        $topics = self::topics($course);
        $ids = array_unique(array_map('intval', $ids));
        if (array_diff($ids, $topics->pluck('id')->all())) {
            throw \Illuminate\Validation\ValidationException::withMessages(['topic_ids' => 'Escolhe matéria da estrutura curricular atual desta UC.']);
        }
        $expanded = $ids;
        do {
            $previous = count($expanded);
            $expanded = array_unique([...$expanded, ...$topics->whereIn('parent_id', $expanded)->pluck('id')->all()]);
        } while (count($expanded) !== $previous);
        return self::leaves($course)->whereIn('id', $expanded)->pluck('id')->all();
    }
}
