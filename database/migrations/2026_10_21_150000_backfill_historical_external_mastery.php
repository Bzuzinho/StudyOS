<?php

use App\Models\Course;
use App\Services\Learning\Curriculum;
use App\Services\Practice\TopicMasteryService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Reconstitute missing topic mastery rows from ChatGPT reports imported before
     * the normalization/mastery pipeline was introduced. Never change reports,
     * curricular coverage or official university assessments.
     */
    public function up(): void
    {
        $mastery = app(TopicMasteryService::class);

        DB::table('external_assessments')->select('course_id')->distinct()
            ->orderBy('course_id')->pluck('course_id')
            ->each(function ($courseId) use ($mastery) {
                $course = Course::query()->with(['topics' => fn ($query) =>
                    $query->where('status', 'active')->with('mastery')])->find($courseId);
                if (! $course) {
                    return;
                }

                $leaves = Curriculum::leaves($course)->keyBy(
                    fn ($topic) => (string) ($topic->metadata['curricular_number'] ?? '')
                );

                $numbers = [];
                DB::table('external_assessments')->where('course_id', $courseId)
                    ->orderBy('id')->select('report')->chunk(100, function ($reports) use (&$numbers) {
                        foreach ($reports as $row) {
                            $report = json_decode((string) $row->report, true);
                            foreach (is_array($report) ? ($report['topics'] ?? []) : [] as $entry) {
                                if (! is_array($entry)) {
                                    continue;
                                }
                                $number = trim((string) ($entry['number'] ?? ''));
                                if ($number !== '') {
                                    $numbers[$number] = true;
                                }
                            }
                        }
                    });

                foreach (array_keys($numbers) as $number) {
                    $topic = $leaves->get($number);
                    // Leave calculated mastery untouched. This migration only repairs
                    // the missing historical rows created by the original v1 importer.
                    if ($topic && ! $topic->mastery) {
                        $mastery->recalculate($topic);
                    }
                }
            });
    }

    public function down(): void
    {
        // Evidence derived from historical reports must not be erased by rollback.
    }
};
