<?php

namespace App\Services\Academic;

use App\Models\Course;
use App\Models\LessonSummary;
use App\Models\Material;
use App\Models\MaterialVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LearningContextBootstrapper
{
    public function run(): array
    {
        return DB::transaction(function () {
            $intro = $this->course('14020');
            $macro = $this->course('10149');
            $accounting = $this->course('6709');

            $introMaterial = Material::query()->updateOrCreate(
                ['source' => 'moodle_audit', 'external_id' => 'ig:integrated-material:20261005'],
                [
                    'course_id' => $intro->id,
                    'type' => 'presentation',
                    'title' => 'Material integrado atualizado',
                    'url' => null,
                    'status' => 'active',
                    'published_at' => null,
                    'metadata' => [
                        'observed_at' => '2026-10-05T11:01:00+01:00',
                        'change_observed' => 'Alteração assinalada no slide 72',
                        'evidence' => 'audited_authenticated_moodle',
                    ],
                ],
            );

            $this->version(
                $introMaterial,
                'ig-integrated-observed-20261005-1101-slide72',
                'Observado em 05/10/2026 11:01',
                Carbon::createFromFormat('Y-m-d H:i', '2026-10-05 11:01', config('app.timezone')),
                ['change_observed' => 'Alteração assinalada no slide 72'],
            );

            $macroMaterial = Material::query()->updateOrCreate(
                ['source' => 'moodle_audit', 'external_id' => 'macro:slides-folder:audit'],
                [
                    'course_id' => $macro->id,
                    'type' => 'folder',
                    'title' => 'Pasta de slides',
                    'url' => null,
                    'status' => 'active',
                    'published_at' => null,
                    'metadata' => [
                        'observed_items' => 6,
                        'observed_format' => 'PDF',
                        'download_folder_available' => true,
                        'evidence' => 'audited_authenticated_moodle',
                    ],
                ],
            );

            $this->version(
                $macroMaterial,
                'macro-slides-folder-six-pdfs-audit',
                'Inventário auditado',
                Carbon::createFromFormat('Y-m-d H:i', '2026-10-05 00:00', config('app.timezone')),
                ['observed_items' => 6, 'observed_format' => 'PDF', 'date_precision' => 'day'],
            );

            LessonSummary::query()->updateOrCreate(
                ['source' => 'inforestudante_audit', 'external_id' => 'cfin1:summaries:20260916-20261002'],
                [
                    'course_id' => $accounting->id,
                    'title' => 'Sumários observados · 16/09 a 02/10',
                    'content' => implode("\n", [
                        'Contabilidade, balanço e resultados',
                        'Sistema de Normalização Contabilística (SNC)',
                        'Estrutura conceptual',
                        'Informação financeira',
                        'Fundo de maneio',
                        'Movimentação contabilística',
                        'Exercícios 1.2, 1.3, 1.6 e 1.7',
                    ]),
                    'occurred_at' => Carbon::createFromFormat(
                        'Y-m-d H:i',
                        '2026-10-02 23:59',
                        config('app.timezone'),
                    )->utc(),
                    'metadata' => [
                        'observed_summary_count' => 6,
                        'date_range' => ['2026-09-16', '2026-10-02'],
                        'evidence' => 'audited_inforestudante',
                        'aggregation' => true,
                    ],
                ],
            );

            return [
                'materials_upserted' => 2,
                'material_versions_upserted' => 2,
                'lesson_summaries_upserted' => 1,
            ];
        });
    }

    private function version(
        Material $material,
        string $sourceHash,
        string $label,
        Carbon $observedAt,
        array $metadata,
    ): void {
        MaterialVersion::query()->updateOrCreate(
            ['material_id' => $material->id, 'source_hash' => hash('sha256', $sourceHash)],
            [
                'version_label' => $label,
                'observed_at' => $observedAt->utc(),
                'metadata' => $metadata,
            ],
        );
    }

    private function course(string $code): Course
    {
        return Course::query()->where('academic_code', $code)->first()
            ?? throw new RuntimeException("Course {$code} must exist before learning context bootstrap.");
    }
}
