<?php
namespace Tests\Unit;
use App\Models\Course;
use App\Models\Topic;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\StudySession;
use App\Services\Learning\Curriculum;
use App\Services\Learning\StudyRecommendationService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
class LearningExperienceTest extends TestCase
{
    public function createApplication() {
        $app = require dirname(__DIR__,2).'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }
    protected function setUp(): void {
        parent::setUp();
        config(['app.key'=>'base64:'.base64_encode(str_repeat('a',32)), 'session.driver'=>'array',
            'studyos_ai.enabled'=>false, 'database.default'=>'sqlite',
            'database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
        $this->artisan('migrate',['--force'=>true])->assertExitCode(0);
    }
    private function curriculum(): array {
        $course=Course::create(['name'=>'Introdução à Gestão','academic_code'=>'14020','semester'=>1,'status'=>'active']);
        $historical=$course->topics()->create(['source'=>'manual','title'=>'Histórico antigo','status'=>'active','taught_at'=>now()]);
        $parent=$course->topics()->create(['source'=>'chatgpt_package','external_id'=>'14020:IG-01','title'=>'Fundamentos','status'=>'active']);
        $child=$course->topics()->create(['source'=>'chatgpt_package','external_id'=>'14020:IG-01.01','parent_id'=>$parent->id,'title'=>'Organizações','status'=>'active']);
        return [$course,$parent,$child,$historical];
    }
    private function question(Course $course, Topic $topic, string $type='true_false'): Exercise {
        $ex=$course->exercises()->create(['source'=>'manual','external_id'=>(string) \Illuminate\Support\Str::uuid(),'type'=>$type,'prompt'=>'Enunciado original','expected_answer'=>'verdadeiro','max_points'=>1,'status'=>'active','metadata'=>['difficulty'=>'medio']]);
        $ex->topics()->attach($topic);
        return $ex;
    }
    public function test_canonical_tree_excludes_history_from_every_entrypoint_and_recommendations(): void {
        [$course,$parent,$child,$historical]=$this->curriculum();
        $child->update(['taught_at'=>now()]);
        $this->assertSame([$child->id],Curriculum::leaves($course->fresh())->pluck('id')->all());
        $plan=app(StudyRecommendationService::class)->forCourse($course->fresh());
        $this->assertSame(1,$plan['topic_count']);
        $this->assertSame($child->id,$plan['recommendations'][0]['topic']->id);
        foreach (['/courses/'.$course->id,'/study/create?course_id='.$course->id,'/practice/treinar?course_id='.$course->id] as $url) {
            $this->get($url)->assertOk()->assertSee('Organizações')->assertDontSee('Histórico antigo');
        }
        $this->get('/courses/'.$course->id)->assertSee('Substituir conteúdos da UC')->assertSee('Acrescentar conteúdos à UC')->assertDontSee('Importar / atualizar pacote')->assertDontSee('excertos indexados');
        $this->assertNotNull($historical->fresh());
        $this->postJson('/practice/treinar',['course_id'=>$course->id,'topic_ids'=>[$historical->id],'count'=>1])->assertUnprocessable();
    }
    public function test_partial_theme_and_clearing_preserve_history(): void {
        [$course,$parent,$child,$historical]=$this->curriculum();
        $other=$course->topics()->create(['source'=>'chatgpt_package','external_id'=>'14020:IG-01.02','parent_id'=>$parent->id,'title'=>'Funções','status'=>'active']);
        $ids=[$parent->id,$child->id,$other->id];
        $this->patch('/courses/'.$course->id.'/topics/coverage',['topic_ids'=>$ids,'taught_topic_ids'=>[$child->id]])->assertRedirect();
        $this->assertNull($parent->fresh()->taught_at);
        $this->assertNotNull($historical->fresh()->taught_at);
        $this->patch('/courses/'.$course->id.'/topics/coverage',['topic_ids'=>$ids,'taught_topic_ids'=>[$child->id,$other->id]])->assertRedirect();
        $this->assertNotNull($parent->fresh()->taught_at);
        $this->patch('/courses/'.$course->id.'/topics/coverage',['topic_ids'=>$ids])->assertRedirect();
        $this->assertNull($child->fresh()->taught_at);
        $this->assertNotNull($historical->fresh()->taught_at);
    }
    public function test_pool_count_filters_shortage_confirmation_and_snapshot_survive_updates(): void {
        [$course,$parent,$child]=$this->curriculum();
        $ex=$this->question($course,$child);
        $params=['course_id'=>$course->id,'topic_ids'=>[$parent->id],'difficulty'=>'intermediate','types'=>['true_false'],'count'=>5];
        $this->getJson('/practice/treinar/disponiveis?'.http_build_query($params))->assertOk()->assertJson(['count'=>1]);
        $this->postJson('/practice/treinar',$params)->assertUnprocessable();
        $this->post('/practice/treinar',[...$params,'accept_available'=>1])->assertRedirect();
        $run=DB::table('practice_runs')->first();
        $ex->update(['prompt'=>'Enunciado alterado','expected_answer'=>'falso','max_points'=>10]);
        $this->get('/practice/sessao/'.$run->id)->assertOk()->assertSee('Enunciado original')->assertDontSee('Enunciado alterado');
        $this->post('/practice/sessao/'.$run->id.'/responder',['answer'=>'verdadeiro','question_id'=>$ex->id])->assertRedirect();
        $this->get('/practice/sessao/'.$run->id)->assertOk()->assertSee('20,0')->assertSee('Enunciado original');
        $this->assertSame('1.00',ExerciseAttempt::first()->max_points);
    }
    public function test_ungraded_open_answer_has_no_global_grade_and_manual_review_refreshes_result(): void {
        [$course,$parent,$child]=$this->curriculum();$ex=$this->question($course,$child,'open_text');
        $this->post('/practice/treinar',['course_id'=>$course->id,'topic_ids'=>[$child->id],'count'=>1])->assertRedirect();
        $run=DB::table('practice_runs')->first();
        $this->post('/practice/sessao/'.$run->id.'/responder',['answer'=>'Uma organização'])->assertRedirect();
        $this->get('/practice/sessao/'.$run->id)->assertOk()->assertSee('Classificação global ainda não disponível')->assertDontSee('20,0 / 20');
        $attempt=ExerciseAttempt::first();
        $this->put('/practice/attempts/'.$attempt->id.'/review',['score'=>1,'feedback'=>'Revisto pelos critérios'])->assertRedirect('/practice/sessao/'.$run->id);
        $this->get('/practice/sessao/'.$run->id)->assertOk()->assertSee('20,0')->assertSee('Revisto pelos critérios');
    }
    public function test_study_now_expands_multiple_topics_and_requires_explicit_completion(): void {
        [$course,$parent,$child]=$this->curriculum();
        $this->post('/study',['course_id'=>$course->id,'topic_ids'=>[$parent->id],'type'=>'reading','start_now'=>1,'planned_minutes'=>30])->assertRedirect();
        $session=StudySession::first();
        $this->assertSame('planned',$session->status);
        $this->get('/study/'.$session->id)->assertOk()->assertSee('Organizações');
        $this->assertSame('started',$session->fresh()->status);
        $this->assertSame([$child->id],$session->topics()->pluck('topics.id')->all());
        $this->assertNull($child->fresh()->taught_at);
        $this->get('/practice/treinar?study_session_id='.$session->id)->assertOk()->assertViewHas('selectedTopics',fn($ids)=>$ids->all()===[$child->id]);
        $this->get('/study')->assertOk()->assertViewHas('completedMinutes',0);
        $this->patch('/study/'.$session->id.'/complete',['actual_minutes'=>22])->assertRedirect();
        $this->get('/study')->assertOk()->assertViewHas('completedMinutes',22);
        $this->delete('/study/'.$session->id)->assertStatus(409);
        $this->assertNotNull($session->fresh());
    }
    public function test_stale_question_submission_cannot_answer_next_question(): void {
        [$course,$parent,$child]=$this->curriculum();$a=$this->question($course,$child);$this->question($course,$child);
        $this->post('/practice/treinar',['course_id'=>$course->id,'topic_ids'=>[$child->id],'count'=>2]);
        $run=DB::table('practice_runs')->first();$first=json_decode($run->exercise_ids,true)[0];
        $url='/practice/sessao/'.$run->id.'/responder';
        $this->post($url,['question_id'=>$first,'answer'=>'verdadeiro'])->assertRedirect();
        $this->post($url,['question_id'=>$first,'answer'=>'verdadeiro'])->assertStatus(409);
        $this->assertDatabaseCount('exercise_attempts',1);
    }
    public function test_unused_questions_are_preferred_and_revised_errors_are_not_repeated(): void {
        [$course,$parent,$child]=$this->curriculum();
        $used=$this->question($course,$child);$unused=$this->question($course,$child);
        $runId=DB::table('practice_runs')->insertGetId(['course_id'=>$course->id,
            'exercise_ids'=>json_encode([$used->id]), 'responses'=>json_encode([$used->id=>['score'=>0,'max_points'=>1,'grading_status'=>'graded']]),
            'current_index'=>1,'status'=>'completed','created_at'=>now(),'updated_at'=>now()]);
        ExerciseAttempt::create(['exercise_id'=>$used->id,'submitted_answer'=>'verdadeiro','score'=>1,'max_points'=>1,
            'percentage'=>100,'grading_status'=>'graded','grading_method'=>'manual_review','attempted_at'=>now(),
            'metadata'=>['practice_run_id'=>$runId]]);
        $params=['course_id'=>$course->id,'topic_ids'=>[$child->id],'count'=>1];
        $this->getJson('/practice/treinar/disponiveis?'.http_build_query([...$params,'repeat_run_id'=>$runId]))->assertJson(['count'=>0]);
        $this->post('/practice/treinar',$params)->assertRedirect();
        $run=DB::table('practice_runs')->latest('id')->first();
        $this->assertSame([$unused->id],json_decode($run->exercise_ids,true));
    }

    public function test_ai_grade_stays_provisional_until_review_and_does_not_create_mastery(): void {
        [$course,$parent,$child]=$this->curriculum();$ex=$this->question($course,$child,'open_text');
        $this->mock(\App\Services\Practice\OpenAnswerAiGrader::class, function($mock) {
            $mock->shouldReceive('grade')->once()->andReturn(['score'=>1,'percentage'=>100,'grading_status'=>'graded',
                'grading_method'=>'ai_rubric_provisional','feedback'=>'Avaliação provisória']);
        });
        $this->post('/practice/treinar',['course_id'=>$course->id,'topic_ids'=>[$child->id],'count'=>1]);
        $run=DB::table('practice_runs')->first();
        $this->post('/practice/sessao/'.$run->id.'/responder',['answer'=>'Resposta aberta']);
        $this->get('/practice/sessao/'.$run->id)->assertOk()->assertSee('Classificação provisória')->assertDontSee('Classificação final');
        $this->assertDatabaseCount('topic_masteries',0);
        $attempt=ExerciseAttempt::first();
        $this->put('/practice/attempts/'.$attempt->id.'/review',['score'=>1])->assertRedirect();
        $this->get('/practice/sessao/'.$run->id)->assertOk()->assertSee('Classificação final');
        $this->assertDatabaseCount('topic_masteries',1);
    }

    public function test_manual_link_uses_the_same_stable_topic_identifier_as_the_tree(): void {
        [$course,$parent,$child]=$this->curriculum();
        $material=$course->materials()->create(['source'=>'chatgpt_package','external_id'=>$child->external_id,
            'title'=>'Manual','type'=>'document','status'=>'active']);
        $material->versions()->create(['source_hash'=>hash('sha256','manual'),'content_text'=>"# Conteúdo para estudar\n\nConceitos fundamentais.",
            'extraction_status'=>'manual_text','observed_at'=>now()]);
        $this->get('/courses/'.$course->id.'/package-topics/'.$child->id)->assertOk()->assertSee('<h2>Conteúdo para estudar</h2>',false);
    }

    public function test_old_session_keeps_its_topics_but_training_uses_the_current_curriculum(): void {
        [$course,$parent,$child,$old]=$this->curriculum();
        $session=$course->studySessions()->create(['source'=>'manual','external_id'=>'old-session','type'=>'reading',
            'starts_at'=>now(),'planned_minutes'=>60,'status'=>'planned']);
        $session->topics()->attach($old);
        $this->get('/study')->assertOk()->assertSee('1 tópicos associados')->assertDontSee('Histórico antigo');
        $this->get('/study/'.$session->id)->assertOk()->assertSee('Consultar matéria original da sessão')->assertSee('Histórico antigo');
        $this->get('/practice/treinar?study_session_id='.$session->id)->assertOk()->assertViewHas('selectedTopics',fn($ids)=>$ids->isEmpty());
        $this->assertSame([$old->id],$session->topics()->pluck('topics.id')->all());
    }

}
