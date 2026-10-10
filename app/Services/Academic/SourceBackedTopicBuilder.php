<?php

namespace App\Services\Academic;

use App\Models\Course;
use App\Models\Topic;
use Illuminate\Support\Str;

/**
 * Identifies provisional study topics only from material sections and recorded
 * lesson summaries. It never claims these are the official complete syllabus.
 */
class SourceBackedTopicBuilder
{
    public function build(Course $course): array
    {
        // Preserve historic records but stop displaying folder labels as curriculum.
        Topic::query()->where('course_id', $course->id)
            ->where('source', 'source_backed')
            ->where('metadata->origin', 'moodle_section')
            ->where('status', 'active')
            ->update(['status' => 'archived']);

        $created = 0;
        $skipped = 0;
        $seen = [];

        $candidates = [];
        // Moodle sections are folders/categories, not curricular topics.
        // Never promote a folder name such as "FUC" to taught content.
        foreach ($course->lessonSummaries()->get() as $summary) {
            foreach (preg_split('/\R/u', (string) $summary->content) ?: [] as $line) {
                $title = trim((string) preg_replace('/\s+/u', ' ', $line));
                $candidates[] = ['title' => $title, 'origin' => 'lesson_summary', 'ref' => (string) $summary->id];
            }
        }

        foreach ($candidates as $candidate) {
            $title = $candidate['title'];
            $normalized = Str::lower(Str::ascii($title));

            if (mb_strlen($title) < 8 || mb_strlen($title) > 180
                || preg_match('/^(geral|general|topico \d+|tema \d+|semana \d+|week \d+|section \d+|sec[cç][aã]o \d+|sum[aá]rio|materiais?|recursos?|apresenta[cç][aã]o|introdu[cç][aã]o|aula \d+|\d+[.\-\/ ]\d+)/iu', $title)
                || in_array($normalized, ['general', 'geral', 'topic', 'tópico', 'materiais', 'resources'], true)
                || isset($seen[$normalized])) {
                $skipped++;
                continue;
            }
            $seen[$normalized] = true;

            if ($course->topics()->where('status', 'active')->whereRaw('LOWER(title) = ?', [Str::lower($title)])->exists()) {
                continue;
            }

            $externalId = hash('sha256', $course->id.'|'.$candidate['origin'].'|'.$normalized);
            $topic = Topic::query()->firstOrNew([
                'source' => 'source_backed',
                'external_id' => $externalId,
            ]);
            if ($topic->exists) {
                continue;
            }
            $topic->fill([
                'course_id' => $course->id,
                'title' => $title,
                'position' => (int) $course->topics()->max('position') + 1,
                'status' => 'active',
                'metadata' => [
                    'provisional' => true,
                    'origin' => $candidate['origin'],
                    'origin_id' => $candidate['ref'],
                    'official_syllabus_verified' => false,
                    'taught_confirmed' => false,
                ],
            ])->save();
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
