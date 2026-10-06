<?php

namespace App\Services\Academic;

use App\Models\Assessment;
use App\Models\ClassOccurrence;
use App\Models\Course;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssessmentCalendarBootstrapper
{
    private const SOURCE = 'estg_assessment_calendar_2026_27';

    /**
     * Confirmed continuous/periodic assessment events for the student's
     * first-semester UCs. Events described in the official PDF as "Aula"
     * inherit their clock time from the matching InforEstudante class occurrence.
     */
    private const PERIODIC = [
        ['id' => 'eag1-rel-20260929', 'code' => '23456', 'date' => '2026-09-29', 'time' => null, 'title' => 'Relatório', 'kind' => 'report'],
        ['id' => 'eag1-rel-20261006', 'code' => '23456', 'date' => '2026-10-06', 'time' => null, 'title' => 'Relatório', 'kind' => 'report'],
        ['id' => 'eag1-rel-20261020', 'code' => '23456', 'date' => '2026-10-20', 'time' => null, 'title' => 'Relatório', 'kind' => 'report'],
        ['id' => 'ig-pe1-20261024', 'code' => '14020', 'date' => '2026-10-24', 'time' => '09:30', 'title' => 'P.E. 1', 'kind' => 'written_test'],
        ['id' => 'eag1-rel-20261027', 'code' => '23456', 'date' => '2026-10-27', 'time' => null, 'title' => 'Relatório', 'kind' => 'report'],
        ['id' => 'cfin1-pe1-20261031', 'code' => '6709', 'date' => '2026-10-31', 'time' => '09:30', 'title' => 'P.E. 1', 'kind' => 'written_test'],
        ['id' => 'eag1-rel-20261103', 'code' => '23456', 'date' => '2026-11-03', 'time' => null, 'title' => 'Relatório', 'kind' => 'report'],
        ['id' => 'macro-pe1-20261107', 'code' => '10149', 'date' => '2026-11-07', 'time' => '09:30', 'title' => 'P.E. 1', 'kind' => 'written_test'],
        ['id' => 'eag1-rel-20261110', 'code' => '23456', 'date' => '2026-11-10', 'time' => null, 'title' => 'Relatório', 'kind' => 'report'],
        ['id' => 'ing-pe1-20261111', 'code' => '11909', 'date' => '2026-11-11', 'time' => null, 'title' => 'P.E. 1', 'kind' => 'written_test'],
        ['id' => 'mq-pe1-20261114', 'code' => '15436', 'date' => '2026-11-14', 'time' => '09:30', 'title' => 'P.E. 1', 'kind' => 'written_test'],
        ['id' => 'eag1-rel-20261117', 'code' => '23456', 'date' => '2026-11-17', 'time' => null, 'title' => 'Relatório', 'kind' => 'report'],
        ['id' => 'eag1-pe1-20261121', 'code' => '23456', 'date' => '2026-11-21', 'time' => '09:30', 'title' => 'P.E. 1', 'kind' => 'written_test'],
        ['id' => 'eag1-rel-20261124', 'code' => '23456', 'date' => '2026-11-24', 'time' => null, 'title' => 'Relatório', 'kind' => 'report'],
        ['id' => 'ig-entrega-20261211', 'code' => '14020', 'date' => '2026-12-11', 'time' => null, 'title' => 'Entrega — data-limite', 'kind' => 'assignment'],
        ['id' => 'ing-pe2-20270106', 'code' => '11909', 'date' => '2027-01-06', 'time' => '18:30', 'title' => 'P.E. 2', 'kind' => 'written_test'],
        ['id' => 'mq-pe2-20270108', 'code' => '15436', 'date' => '2027-01-08', 'time' => '18:30', 'title' => 'P.E. 2', 'kind' => 'written_test'],
        ['id' => 'cfin1-pe2-20270113', 'code' => '6709', 'date' => '2027-01-13', 'time' => '18:30', 'title' => 'P.E. 2', 'kind' => 'written_test'],
        ['id' => 'ig-pe2-20270116', 'code' => '14020', 'date' => '2027-01-16', 'time' => '09:30', 'title' => 'P.E. 2', 'kind' => 'written_test'],
        ['id' => 'macro-pe2-20270122', 'code' => '10149', 'date' => '2027-01-22', 'time' => '18:30', 'title' => 'P.E. 2', 'kind' => 'written_test'],
        ['id' => 'eag1-pe2-20270128', 'code' => '23456', 'date' => '2027-01-28', 'time' => '18:30', 'title' => 'P.E. 2', 'kind' => 'written_test'],
    ];

    private const CONDITIONAL = [
        // Exame normal.
        ['id' => 'ing-normal-20270106', 'code' => '11909', 'date' => '2027-01-06', 'time' => '18:30', 'regime' => 'normal'],
        ['id' => 'mq-normal-20270108', 'code' => '15436', 'date' => '2027-01-08', 'time' => '18:30', 'regime' => 'normal'],
        ['id' => 'cfin1-normal-20270113', 'code' => '6709', 'date' => '2027-01-13', 'time' => '18:30', 'regime' => 'normal'],
        ['id' => 'ig-normal-20270116', 'code' => '14020', 'date' => '2027-01-16', 'time' => '09:30', 'regime' => 'normal'],
        ['id' => 'eag1-normal-20270118', 'code' => '23456', 'date' => '2027-01-18', 'time' => '18:30', 'regime' => 'normal'],
        ['id' => 'macro-normal-20270122', 'code' => '10149', 'date' => '2027-01-22', 'time' => '18:30', 'regime' => 'normal'],

        // Recurso.
        ['id' => 'mq-recurso-20270201', 'code' => '15436', 'date' => '2027-02-01', 'time' => '18:30', 'regime' => 'recurso'],
        ['id' => 'ing-recurso-20270202', 'code' => '11909', 'date' => '2027-02-02', 'time' => '18:30', 'regime' => 'recurso'],
        ['id' => 'cfin1-recurso-20270204', 'code' => '6709', 'date' => '2027-02-04', 'time' => '18:30', 'regime' => 'recurso'],
        ['id' => 'ig-recurso-20270206', 'code' => '14020', 'date' => '2027-02-06', 'time' => '09:30', 'regime' => 'recurso'],
        ['id' => 'macro-recurso-20270210', 'code' => '10149', 'date' => '2027-02-10', 'time' => '18:30', 'regime' => 'recurso'],
        ['id' => 'eag1-recurso-20270213', 'code' => '23456', 'date' => '2027-02-13', 'time' => '09:30', 'regime' => 'recurso'],

        // Especial.
        ['id' => 'mq-especial-20270215', 'code' => '15436', 'date' => '2027-02-15', 'time' => '18:30', 'regime' => 'especial'],
        ['id' => 'ing-especial-20270216', 'code' => '11909', 'date' => '2027-02-16', 'time' => '18:30', 'regime' => 'especial'],
        ['id' => 'cfin1-especial-20270218', 'code' => '6709', 'date' => '2027-02-18', 'time' => '18:30', 'regime' => 'especial'],
        ['id' => 'ig-especial-20270222', 'code' => '14020', 'date' => '2027-02-22', 'time' => '18:30', 'regime' => 'especial'],
        ['id' => 'macro-especial-20270224', 'code' => '10149', 'date' => '2027-02-24', 'time' => '18:30', 'regime' => 'especial'],
        ['id' => 'eag1-especial-20270226', 'code' => '23456', 'date' => '2027-02-26', 'time' => '18:30', 'regime' => 'especial'],
    ];

    public function run(): array
    {
        return DB::transaction(function () {
            $stats = [
                'periodic_created_or_updated' => 0,
                'conditional_created_or_updated' => 0,
                'calendar_events_created_or_updated' => 0,
                'awaiting_class_time' => 0,
            ];

            foreach (self::PERIODIC as $definition) {
                $course = $this->course($definition['code']);
                $timing = $this->resolveTiming($course, $definition['date'], $definition['time']);

                $assessment = Assessment::query()->updateOrCreate(
                    ['source' => self::SOURCE, 'external_id' => $definition['id']],
                    [
                        'course_id' => $course->id,
                        'type' => $definition['kind'],
                        'title' => $definition['title'],
                        'due_at' => $timing['starts_at'],
                        'confirmed' => true,
                        'metadata' => [
                            'regime' => 'periodic',
                            'conditional' => false,
                            'academic_year' => '2026/2027',
                            'source_document' => 'CAv_202627_GEPL.pdf',
                            'source_version' => '23/09/2026',
                            'date' => $definition['date'],
                            'clock_time_source' => $timing['basis'],
                        ],
                    ],
                );

                $stats['periodic_created_or_updated']++;

                if (! $timing['starts_at']) {
                    $stats['awaiting_class_time']++;
                    continue;
                }

                ClassOccurrence::query()->updateOrCreate(
                    ['source' => self::SOURCE, 'external_uid' => 'assessment:'.$definition['id']],
                    [
                        'course_id' => $course->id,
                        'title' => $definition['title'],
                        'location' => $timing['location'],
                        'starts_at' => $timing['starts_at'],
                        'ends_at' => $timing['ends_at'],
                        'status' => 'scheduled',
                        'last_seen_at' => now(),
                        'source_payload' => [
                            'event_type' => 'assessment',
                            'assessment_id' => $assessment->id,
                            'regime' => 'periodic',
                            'conditional' => false,
                            'source_document' => 'CAv_202627_GEPL.pdf',
                            'source_version' => '23/09/2026',
                            'clock_time_source' => $timing['basis'],
                        ],
                    ],
                );

                $stats['calendar_events_created_or_updated']++;
            }

            foreach (self::CONDITIONAL as $definition) {
                $course = $this->course($definition['code']);
                $startsAt = $this->at($definition['date'], $definition['time']);

                Assessment::query()->updateOrCreate(
                    ['source' => self::SOURCE, 'external_id' => $definition['id']],
                    [
                        'course_id' => $course->id,
                        'type' => 'exam',
                        'title' => 'Exame '.ucfirst($definition['regime']),
                        'due_at' => $startsAt,
                        'confirmed' => true,
                        'metadata' => [
                            'regime' => $definition['regime'],
                            'conditional' => true,
                            'academic_year' => '2026/2027',
                            'source_document' => 'CAv_202627_GEPL.pdf',
                            'source_version' => '23/09/2026',
                        ],
                    ],
                );

                $stats['conditional_created_or_updated']++;
            }

            return $stats;
        });
    }

    private function course(string $code): Course
    {
        return Course::query()->where('academic_code', $code)->first()
            ?? throw new RuntimeException("Course {$code} must exist before assessment bootstrap.");
    }

    private function resolveTiming(Course $course, string $date, ?string $time): array
    {
        if ($time) {
            return [
                'starts_at' => $this->at($date, $time),
                'ends_at' => null,
                'location' => null,
                'basis' => 'official_pdf',
            ];
        }

        $timezone = config('app.timezone', 'Europe/Lisbon');
        $day = Carbon::createFromFormat('Y-m-d', $date, $timezone);

        $class = ClassOccurrence::query()
            ->scheduledClasses()
            ->where('course_id', $course->id)
            ->where('source', 'inforestudante_ical')
            ->whereBetween('starts_at', [
                $day->copy()->startOfDay()->utc(),
                $day->copy()->endOfDay()->utc(),
            ])
            ->orderBy('starts_at')
            ->first();

        return [
            'starts_at' => $class?->starts_at,
            'ends_at' => $class?->ends_at,
            'location' => $class?->location,
            'basis' => $class ? 'official_pdf_date_plus_inforestudante_class_time' : 'official_pdf_date_only',
        ];
    }

    private function at(string $date, string $time): Carbon
    {
        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $date.' '.$time,
            config('app.timezone', 'Europe/Lisbon'),
        )->utc();
    }
}
