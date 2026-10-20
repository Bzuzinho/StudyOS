<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Topic;
use App\Models\TopicMastery;
use App\Services\Learning\KnowledgeDashboardService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

class KnowledgeDashboardTest extends TestCase
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

    public function test_dashboard_separates_taught_from_demonstrated_mastery_and_missing_evidence(): void
    {
        $course = Course::create(['academic_code'=>'14020','name'=>'Gestão','status'=>'active']);
        $noEvidence = Topic::create(['course_id'=>$course->id,'title'=>'Tema A','source'=>'confirmed_programme','external_id'=>'confirmed:14020:1.1','status'=>'active','metadata'=>['curricular_number'=>'1.1'],'taught_at'=>now()]);
        $strong = Topic::create(['course_id'=>$course->id,'title'=>'Tema B','source'=>'confirmed_programme','external_id'=>'confirmed:14020:1.2','status'=>'active','metadata'=>['curricular_number'=>'1.2'],'taught_at'=>now()]);
        TopicMastery::create(['topic_id'=>$strong->id,'status'=>'strong','score_percent'=>90,'evidence_attempts'=>3,'evidence_exercises'=>3,'metadata'=>['evidence_units'=>3],'calculated_at'=>now()]);

        $overview = app(KnowledgeDashboardService::class)->overview();

        $this->assertSame(2,$overview['total']);
        $this->assertSame(2,$overview['taught']);
        $this->assertSame(1,$overview['assessed']);
        $this->assertSame(1,$overview['mastered']);
        $this->assertSame(1,$overview['courses']->first()['status_counts']['no_evidence']);
        $this->assertSame($noEvidence->id,$overview['priorities']->first()['topic_id']);
        $this->get('/')->assertOk()->assertSee('Conhecimento avaliado')->assertSee('Domínio demonstrado');
        $this->get('/courses/'.$course->id)->assertOk()->assertSee('Diagnóstico de conhecimento');
    }

    public function test_diagnostic_without_global_grade_displays_no_grade(): void
    {
        $course = Course::create(['academic_code'=>'14020','name'=>'Gestão','status'=>'active']);
        Topic::create(['course_id'=>$course->id,'title'=>'Tema A','source'=>'confirmed_programme','external_id'=>'confirmed:14020:1.1','status'=>'active','metadata'=>['curricular_number'=>'1.1']]);
        DB::table('external_assessments')->insert([
            'course_id'=>$course->id,'external_id'=>'STUDYOS-KNOWLEDGE-001','assessed_on'=>'2026-10-19',
            'score_20'=>null,'report'=>json_encode(['schema'=>'studyos.external-assessment.v2','difficulties'=>['Regra da cadeia'],'recommendations'=>['Rever exemplos'],'topics'=>[]]),
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->get('/courses/'.$course->id)->assertOk()->assertSee('sem nota global')->assertSee('Regra da cadeia');
        $this->get('/practice')->assertOk()->assertSee('sem classificação global');
    }
}
