<?php

namespace App\Services\Academic;

use App\Models\Assessment;
use App\Models\ClassOccurrence;
use App\Models\Course;
use App\Models\SourceCourse;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MoodleAuditBootstrapper
{
    private const COURSES = [
        ['code' => '11909', 'id' => '16', 'name' => 'C1 English 1st semester'],
        ['code' => '23456', 'id' => '1103', 'name' => 'Estatística Aplicada à Gestão I'],
        ['code' => '15436', 'id' => '1994', 'name' => 'Métodos Quantitativos'],
        ['code' => '10149', 'id' => '2276', 'name' => 'Macroeconomia'],
        ['code' => '14020', 'id' => '1829', 'name' => 'Introdução à Gestão'],
        ['code' => '6709', 'id' => '2304', 'name' => 'Contabilidade Financeira I'],
    ];

    public function run(): array
    {
        return DB::transaction(function () {
            foreach (self::COURSES as $definition) {
                $course = $this->course($definition['code']);

                SourceCourse::query()->updateOrCreate(
                    ['source' => 'moodle', 'external_id' => $definition['id']],
                    [
                        'course_id' => $course->id,
                        'external_name' => $definition['name'],
                        'metadata' => [
                            'academic_year' => '2026/2027',
                            'audited' => true,
                            'course_path' => '/2026-27/course/view.php?id='.$definition['id'],
                        ],
                    ],
                );
            }

            $statistics = $this->course('23456');
            $opensAt = $this->at('2026-10-06 12:46');
            $dueAt = $this->at('2026-10-10 02:02');

            $assessment = Assessment::query()->updateOrCreate(
                ['source' => 'moodle_audit', 'external_id' => 'quiz:51483'],
                [
                    'course_id' => $statistics->id,
                    'type' => 'quiz',
                    'title' => 'Trabalho3',
                    'opens_at' => $opensAt,
                    'due_at' => $dueAt,
                    'confirmed' => true,
                    'metadata' => [
                        'conditional' => false,
                        'moodle_course_id' => 1103,
                        'moodle_activity_id' => 51483,
                        'attempts_allowed' => 1,
                        'duration_minutes' => 15,
                        'password_required' => true,
                        'activity_path' => '/2026-27/mod/quiz/view.php?id=51483',
                        'evidence' => 'audited_authenticated_moodle',
                    ],
                ],
            );

            ClassOccurrence::query()->updateOrCreate(
                ['source' => 'moodle_audit', 'external_uid' => 'quiz-deadline:51483'],
                [
                    'course_id' => $statistics->id,
                    'title' => 'Trabalho3 — prazo',
                    'starts_at' => $dueAt,
                    'ends_at' => null,
                    'location' => null,
                    'status' => 'scheduled',
                    'last_seen_at' => now(),
                    'source_payload' => [
                        'event_type' => 'assessment_deadline',
                        'assessment_id' => $assessment->id,
                        'moodle_activity_id' => 51483,
                        'conditional' => false,
                    ],
                ],
            );

            $english = $this->course('11909');

            Task::query()->updateOrCreate(
                ['source' => 'moodle_audit', 'external_id' => 'assignment:c1-assignment-1'],
                [
                    'course_id' => $english->id,
                    'type' => 'assignment',
                    'title' => 'Assignment 1',
                    'description' => 'Texto de 200–300 palavras sobre confiança nas competências de Inglês e expectativas para C1.',
                    'opens_at' => null,
                    'due_at' => null,
                    'status' => 'pending',
                    'confirmed' => true,
                    'metadata' => [
                        'moodle_course_id' => 16,
                        'submission_observed' => false,
                        'grade_observed' => false,
                        'deadline_observed' => false,
                        'evidence' => 'audited_authenticated_moodle',
                    ],
                ],
            );

            return [
                'moodle_courses_linked' => count(self::COURSES),
                'assessments_upserted' => 1,
                'tasks_upserted' => 1,
                'calendar_deadlines_upserted' => 1,
            ];
        });
    }

    private function course(string $code): Course
    {
        return Course::query()->where('academic_code', $code)->first()
            ?? throw new RuntimeException("Course {$code} must exist before Moodle bootstrap.");
    }

    private function at(string $localDateTime): Carbon
    {
        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $localDateTime,
            config('app.timezone', 'Europe/Lisbon'),
        )->utc();
    }
}
