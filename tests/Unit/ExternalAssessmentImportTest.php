<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Topic;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

class ExternalAssessmentImportTest extends TestCase
{
    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'session.driver' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_v1_import_remains_compatible_and_updates_mastery_without_changing_university_grades(): void
    {
        [$course] = $this->courseWithTopic();
        $report = [
            'schema'=>'studyos.external-assessment.v1', 'assessment_id'=>'STUDYOS-TEST-001',
            'academic_code'=>'15436', 'assessed_on'=>'2026-10-16','score_20'=>16,
            'topics'=>[['number'=>'1.1','score_percent'=>80,'feedback'=>'Derivação correta, rever a regra da cadeia.']],
            'strengths'=>['Derivadas básicas'], 'difficulties'=>['Regra da cadeia'],
            'next_steps'=>['Resolver problemas mistos'],
        ];

        $this->post('/practice/chatgpt/'.$course->id, ['report'=>json_encode($report)])->assertRedirect();

        $this->assertSame(1, DB::table('external_assessments')->count());
        $this->assertEquals(16.0, (float) DB::table('external_assessments')->value('score_20'));
        $mastery = DB::table('topic_masteries')->first();
        $this->assertNotNull($mastery);
        $this->assertSame('insufficient_evidence', $mastery->status);
        $this->assertEquals(80.0, (float) $mastery->score_percent);
        $this->assertSame(0, DB::table('assessments')->count());
    }

    public function test_v2_evidence_count_can_support_a_competent_mastery_level(): void
    {
        [$course] = $this->courseWithTopic();
        $report = $this->v2Report('STUDYOS-TEST-003', 80, 3, 16);

        $this->post('/practice/chatgpt/'.$course->id, ['report'=>json_encode($report)])->assertRedirect();

        $mastery = DB::table('topic_masteries')->first();
        $this->assertSame('competent', $mastery->status);
        $this->assertEquals(80.0, (float) $mastery->score_percent);
        $metadata = json_decode($mastery->metadata, true);
        $this->assertSame(3, $metadata['external_evidence_units']);
        $this->assertSame('weighted_estimate', $metadata['score_kind']);
    }

    public function test_no_evidence_is_not_stored_as_zero_knowledge(): void
    {
        [$course] = $this->courseWithTopic();
        $report = $this->v2Report('STUDYOS-TEST-004', null, 0, null, 'diagnostic');

        $this->post('/practice/chatgpt/'.$course->id, ['report'=>json_encode($report)])->assertRedirect();

        $this->assertNull(DB::table('external_assessments')->value('score_20'));
        $mastery = DB::table('topic_masteries')->first();
        $this->assertSame('no_evidence', $mastery->status);
        $this->assertNull($mastery->score_percent);
    }

    public function test_preview_does_not_persist_and_shows_proposed_state(): void
    {
        [$course] = $this->courseWithTopic();
        $report = $this->v2Report('STUDYOS-TEST-005', 88, 4, 17.6);

        $this->post('/practice/chatgpt/'.$course->id.'/preview', ['report'=>json_encode($report)])
            ->assertOk()
            ->assertSee('Pré-visualização da importação')
            ->assertSee('Forte');

        $this->assertSame(0, DB::table('external_assessments')->count());
        $this->assertSame(0, DB::table('topic_masteries')->count());
    }

    public function test_duplicate_assessment_id_is_rejected_instead_of_overwriting_history(): void
    {
        [$course] = $this->courseWithTopic();
        $report = $this->v2Report('STUDYOS-DUP-001', 80, 3, 16);

        $this->post('/practice/chatgpt/'.$course->id, ['report'=>json_encode($report)])->assertRedirect();
        $this->post('/practice/chatgpt/'.$course->id, ['report'=>json_encode($report)])->assertSessionHasErrors('report');

        $this->assertSame(1, DB::table('external_assessments')->count());
    }

    public function test_rejects_unknown_subtopic_and_course_mismatch(): void
    {
        [$course] = $this->courseWithTopic();
        $report=$this->v2Report('STUDYOS-TEST-002', 100, 3, 20);
        $report['topics'][0]['number']='9.9';

        $this->post('/practice/chatgpt/'.$course->id, ['report'=>json_encode($report)])->assertSessionHasErrors('report');
        $this->assertSame(0, DB::table('external_assessments')->count());
    }

    public function test_plain_text_with_missing_identifier_offers_normalization_without_importing(): void
    {
        [$course] = $this->courseWithTopic();
        $text = "Avaliação de Métodos Quantitativos em 2026-10-19\nNota: 16/20\n1.1 Derivadas — 80% — 3 questões\nDificuldades:\n- Regra da cadeia";

        $this->post('/practice/chatgpt/'.$course->id.'/preview', ['report'=>$text])
            ->assertOk()
            ->assertSee('assessment_id')
            ->assertSee('Prompt de normalização');

        $this->assertSame(0, DB::table('external_assessments')->count());
    }

    public function test_backfill_recovers_v1_history_without_overwriting_official_assessments(): void
    {
        [$course, $topic] = $this->courseWithTopic();
        DB::table('external_assessments')->insert([
            'course_id' => $course->id,
            'external_id' => 'STUDYOS-HISTORICAL-V1',
            'assessed_on' => '2026-10-16',
            'score_20' => 16,
            'report' => json_encode([
                'schema' => 'studyos.external-assessment.v1',
                'topics' => [['number' => '1.1', 'score_percent' => 80,
                    'feedback' => 'Conceito compreendido; rever exemplos.']],
            ]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(0, DB::table('topic_masteries')->count());
        $migration = require base_path('database/migrations/2026_10_21_150000_backfill_historical_external_mastery.php');
        $migration->up();

        $mastery = DB::table('topic_masteries')->where('topic_id', $topic->id)->first();
        $this->assertNotNull($mastery);
        $this->assertSame('insufficient_evidence', $mastery->status);
        $this->assertEquals(80, (float) $mastery->score_percent);
        $this->assertSame(0, DB::table('assessments')->count());
        $this->assertSame(1, DB::table('external_assessments')->count());

        // Safe to run again: no duplicates and no reclassification of valid rows.
        $migration->up();
        $this->assertSame(1, DB::table('topic_masteries')->count());
        $this->get('/courses/'.$course->id)->assertOk()->assertSee('80% estimados');
    }

    public function test_backfill_ignores_unmatched_numbers_in_legacy_reports(): void
    {
        [$course] = $this->courseWithTopic();
        DB::table('external_assessments')->insert([
            'course_id' => $course->id,
            'external_id' => 'STUDYOS-HISTORICAL-OTHER',
            'assessed_on' => '2026-10-16',
            'score_20' => null,
            'report' => json_encode([
                'schema' => 'studyos.external-assessment.v1',
                'topics' => [['number' => '9.9', 'score_percent' => 85, 'feedback' => 'Outro programa']],
            ]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_10_21_150000_backfill_historical_external_mastery.php');
        $migration->up();
        $this->assertSame(0, DB::table('topic_masteries')->count());
        $this->assertSame(1, DB::table('external_assessments')->count());
    }

    private function courseWithTopic(): array
    {
        $course = Course::create(['academic_code' => '15436', 'name' => 'Métodos Quantitativos', 'status' => 'active']);
        $topic = Topic::create([
            'course_id' => $course->id,
            'title' => 'Derivadas',
            'source' => 'confirmed_programme',
            'external_id' => 'confirmed:15436:1.1',
            'status' => 'active',
            'metadata' => ['curricular_number' => '1.1'],
        ]);

        return [$course, $topic];
    }

    private function v2Report(string $id, ?float $scorePercent, int $evidenceCount, ?float $score20, string $type = 'practice'): array
    {
        return [
            'schema'=>'studyos.external-assessment.v2',
            'assessment_id'=>$id,
            'academic_code'=>'15436',
            'assessed_on'=>'2026-10-19',
            'activity_type'=>$type,
            'score_20'=>$score20,
            'topics'=>[[
                'number'=>'1.1',
                'score_percent'=>$scorePercent,
                'evidence_count'=>$evidenceCount,
                'feedback'=>$evidenceCount ? 'Resultado observado em exercícios corrigidos.' : 'Sem evidência avaliativa suficiente.',
            ]],
            'strengths'=>[],
            'difficulties'=>[],
            'conceptual_errors'=>[],
            'recommendations'=>[],
            'review_topics'=>[],
            'mastered_topics'=>[],
        ];
    }
}
