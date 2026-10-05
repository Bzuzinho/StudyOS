<?php

namespace App\Services\Academic;

use App\Models\Course;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TopicBootstrapper
{
    private const CFIN_TOPICS = [
        ['id' => 'cfin1-topic-accounting-balance-results', 'title' => 'Contabilidade, balanço e resultados', 'kind' => 'content'],
        ['id' => 'cfin1-topic-snc', 'title' => 'Sistema de Normalização Contabilística (SNC)', 'kind' => 'content'],
        ['id' => 'cfin1-topic-conceptual-framework', 'title' => 'Estrutura conceptual', 'kind' => 'content'],
        ['id' => 'cfin1-topic-financial-information', 'title' => 'Informação financeira', 'kind' => 'content'],
        ['id' => 'cfin1-topic-working-capital', 'title' => 'Fundo de maneio', 'kind' => 'content'],
        ['id' => 'cfin1-topic-accounting-movements', 'title' => 'Movimentação contabilística', 'kind' => 'content'],
        ['id' => 'cfin1-topic-exercises-1-2-1-3-1-6-1-7', 'title' => 'Exercícios 1.2, 1.3, 1.6 e 1.7', 'kind' => 'practice'],
    ];

    public function run(): array
    {
        return DB::transaction(function () {
            $course = Course::query()->where('academic_code', '6709')->first()
                ?? throw new RuntimeException('Contabilidade Financeira I must exist before topic bootstrap.');

            foreach (self::CFIN_TOPICS as $position => $definition) {
                Topic::query()->updateOrCreate(
                    ['source' => 'inforestudante_audit', 'external_id' => $definition['id']],
                    [
                        'course_id' => $course->id,
                        'parent_id' => null,
                        'title' => $definition['title'],
                        'description' => null,
                        'position' => $position + 1,
                        'status' => 'active',
                        'metadata' => [
                            'kind' => $definition['kind'],
                            'curricular_evidence' => 'observed_in_lesson_summaries',
                            'observed_range' => ['2026-09-16', '2026-10-02'],
                            'mastery_evidence' => false,
                        ],
                    ],
                );
            }

            return ['topics_upserted' => count(self::CFIN_TOPICS)];
        });
    }
}
