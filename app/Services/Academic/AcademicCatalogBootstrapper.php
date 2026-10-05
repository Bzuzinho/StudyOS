<?php

namespace App\Services\Academic;

use App\Models\ClassOccurrence;
use App\Models\Course;
use App\Models\SourceCourse;
use App\Services\Calendar\CourseMatcher;
use Illuminate\Support\Facades\DB;

class AcademicCatalogBootstrapper
{
    public function __construct(private readonly CourseMatcher $courseMatcher) {}

    private const COURSES = [
        ['code' => '6709', 'name' => 'Contabilidade Financeira I', 'semester' => 1, 'status' => 'active', 'ects' => 6, 'groups' => ['TP1'], 'aliases' => ['CF I', 'CFI', 'CFIN 1', 'CFIN I']],
        ['code' => '23456', 'name' => 'Estatística Aplicada à Gestão I', 'semester' => 1, 'status' => 'active', 'groups' => ['PL1', 'TP1'], 'aliases' => ['EAG I', 'EAGI', 'EAG 1']],
        ['code' => '11909', 'name' => 'Inglês', 'semester' => 1, 'status' => 'active', 'groups' => ['TP1'], 'aliases' => ['Inglês', 'English', 'ING', 'C1 English', 'C1 English 1st semester']],
        ['code' => '14020', 'name' => 'Introdução à Gestão', 'semester' => 1, 'status' => 'active', 'groups' => ['TP1'], 'aliases' => ['IG']],
        ['code' => '10149', 'name' => 'Macroeconomia', 'semester' => 1, 'status' => 'active', 'groups' => ['TP1'], 'aliases' => ['Macroeconomia', 'MACRO']],
        ['code' => '15436', 'name' => 'Métodos Quantitativos', 'semester' => 1, 'status' => 'active', 'groups' => ['TP1'], 'aliases' => ['MQ']],
        ['code' => '10150', 'name' => 'Contabilidade Financeira II', 'semester' => 2, 'status' => 'planned', 'groups' => [], 'aliases' => ['CF II', 'CFII']],
        ['code' => '17497', 'name' => 'Estatística Aplicada à Gestão II', 'semester' => 2, 'status' => 'planned', 'groups' => [], 'aliases' => ['EAG II', 'EAGII']],
        ['code' => '14021', 'name' => 'Finanças Empresariais I', 'semester' => 2, 'status' => 'planned', 'groups' => [], 'aliases' => ['FE I', 'FEI']],
        ['code' => '15437', 'name' => 'Microeconomia', 'semester' => 2, 'status' => 'planned', 'groups' => [], 'aliases' => ['Microeconomia']],
        ['code' => '15438', 'name' => 'Modelos e Técnicas de Comunicação', 'semester' => 2, 'status' => 'planned', 'groups' => [], 'aliases' => ['MTC']],
        ['code' => '14022', 'name' => 'Tecnologias de Informação em Gestão', 'semester' => 2, 'status' => 'planned', 'groups' => [], 'aliases' => ['TIG']],
    ];

    public function run(): array
    {
        return DB::transaction(function () {
            $created = 0;
            $updated = 0;

            foreach (self::COURSES as $definition) {
                $course = Course::query()->firstOrNew(['academic_code' => $definition['code']]);
                $wasExisting = $course->exists;

                $course->fill([
                    'name' => $definition['name'],
                    'semester' => $definition['semester'],
                    'ects' => $definition['ects'] ?? null,
                    'status' => $definition['status'],
                ]);
                $course->save();

                SourceCourse::query()->updateOrCreate(
                    [
                        'source' => 'inforestudante',
                        'external_id' => $definition['code'],
                    ],
                    [
                        'course_id' => $course->id,
                        'external_name' => $definition['name'],
                        'metadata' => [
                            'academic_year' => '2026/2027',
                            'class_groups' => $definition['groups'],
                            'aliases' => $definition['aliases'],
                            'catalogue_evidence' => 'audited_inforestudante',
                        ],
                    ],
                );

                $wasExisting ? $updated++ : $created++;
            }

            $relinked = 0;
            $unmatched = 0;

            ClassOccurrence::query()
                ->where('source', 'inforestudante_ical')
                ->orderBy('id')
                ->chunkById(200, function ($occurrences) use (&$relinked, &$unmatched) {
                    foreach ($occurrences as $occurrence) {
                        $course = $this->courseMatcher->match($occurrence->title);

                        if (! $course) {
                            $unmatched++;
                            continue;
                        }

                        if ((int) $occurrence->course_id !== (int) $course->id) {
                            $occurrence->update(['course_id' => $course->id]);
                            $relinked++;
                        }
                    }
                });

            return [
                'created' => $created,
                'updated' => $updated,
                'active' => Course::query()->where('status', 'active')->count(),
                'planned' => Course::query()->where('status', 'planned')->count(),
                'calendar_relinked' => $relinked,
                'calendar_unmatched' => $unmatched,
            ];
        });
    }
}
