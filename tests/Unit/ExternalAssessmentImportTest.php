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

    public function test_can_import_external_assessment_without_changing_university_grades(): void
    {
        $course = Course::create(['academic_code' => '15436', 'name' => 'Métodos Quantitativos', 'status' => 'active']);
        Topic::create(['course_id' => $course->id, 'title' => 'Derivadas', 'source' => 'confirmed_programme',
            'external_id' => 'confirmed:15436:1.1', 'status' => 'active', 'metadata' => ['curricular_number' => '1.1']]);
        $report = [
            'schema'=>'studyos.external-assessment.v1', 'assessment_id'=>'STUDYOS-TEST-001',
            'academic_code'=>'15436', 'assessed_on'=>'2026-10-16','score_20'=>16,
            'topics'=>[['number'=>'1.1','score_percent'=>80,'feedback'=>'Derivação correta, rever a regra da cadeia.']],
            'strengths'=>['Derivadas básicas'], 'difficulties'=>['Regra da cadeia'],
            'next_steps'=>['Resolver problemas mistos'],
        ];
        $this->post('/practice/chatgpt/'.$course->id,['report'=>json_encode($report)])->assertRedirect();
        $this->assertSame(1,DB::table('external_assessments')->count());
        $this->assertSame('16.00',(string) DB::table('external_assessments')->value('score_20'));
        $this->assertSame(0,DB::table('topic_masteries')->count());
    }

    public function test_rejects_unknown_subtopic_and_course_mismatch(): void
    {
        $course = Course::create(['academic_code'=>'15436','name'=>'MQ','status'=>'active']);
        $report=['schema'=>'studyos.external-assessment.v1','assessment_id'=>'STUDYOS-TEST-002',
            'academic_code'=>'15436','assessed_on'=>'2026-10-16','score_20'=>20,
            'topics'=>[['number'=>'9.9','score_percent'=>100,'feedback'=>'Teste']],
            'strengths'=>[],'difficulties'=>[],'next_steps'=>[]];
        $this->post('/practice/chatgpt/'.$course->id,['report'=>json_encode($report)])->assertSessionHasErrors('report');
        $this->assertSame(0,DB::table('external_assessments')->count());
    }
}
