<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Exercise;
use App\Models\Topic;
use Illuminate\Foundation\Testing\TestCase;

class PracticeRunTest extends TestCase
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
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'session.driver' => 'array', 'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite','database' => ':memory:',
                'prefix' => '', 'foreign_key_constraints' => true]]);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_a_run_answers_multiple_questions_then_reveals_results(): void
    {
        $course = Course::create(['academic_code'=>'14020','name'=>'Introdução à Gestão','status'=>'active']);
        $parent = Topic::create(['course_id'=>$course->id,'title'=>'Fundamentos',
            'source'=>'chatgpt_package','external_id'=>'14020:IG-01','status'=>'active']);
        $topic = Topic::create(['course_id'=>$course->id,'parent_id'=>$parent->id,'title'=>'Organizações',
            'source'=>'chatgpt_package','external_id'=>'14020:IG-01.01','status'=>'active']);
        $first = Exercise::create(['course_id'=>$course->id,'source'=>'manual','external_id'=>'a',
            'type'=>'true_false','prompt'=>'Uma ONG pode ser uma organização?',
            'expected_answer'=>'verdadeiro','max_points'=>1,'status'=>'active']);
        $second = Exercise::create(['course_id'=>$course->id,'source'=>'manual','external_id'=>'b',
            'type'=>'true_false','prompt'=>'Toda a organização visa lucro?',
            'expected_answer'=>'falso','max_points'=>1,'status'=>'active']);
        $first->topics()->attach($topic);
        $second->topics()->attach($topic);

        $this->post(route('practice.runs.store'), [
            'course_id'=>$course->id,'topic_ids'=>[$parent->id],'count'=>2,
        ])->assertRedirect();
        $run = \Illuminate\Support\Facades\DB::table('practice_runs')->first();
        $this->assertNotNull($run);
        $ids = json_decode($run->exercise_ids, true);
        $this->assertCount(2,$ids);
        $this->get(route('practice.runs.show',$run->id))->assertOk()
            ->assertDontSee('Resposta de referência');
        foreach ($ids as $id) {
            $exercise = Exercise::findOrFail($id);
            $this->post(route('practice.runs.answer',$run->id), [
                'answer'=>$exercise->expected_answer,
            ])->assertRedirect();
        }
        $this->get(route('practice.runs.show',$run->id))->assertOk()
            ->assertSee('Resultados e correção')->assertSee('20,0');
        $this->assertSame('completed',\Illuminate\Support\Facades\DB::table('practice_runs')->find($run->id)->status);
        $this->assertDatabaseCount('exercise_attempts',2);
        $this->post(route('practice.runs.answer',$run->id),['answer'=>'verdadeiro'])->assertStatus(409);
    }
}
