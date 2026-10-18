<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Topic;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

class LightweightChatgptHubTest extends TestCase
{
    public function createApplication()
    {
        $app=require dirname(__DIR__,2).'/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key'=>'base64:'.base64_encode(str_repeat('a',32)),
            'session.driver'=>'array',
            'database.default'=>'sqlite',
            'database.connections.sqlite'=>[
                'driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true
            ],
        ]);
        $this->artisan('migrate',['--force'=>true])->assertExitCode(0);
    }

    public function test_project_link_is_saved_and_displayed_by_course(): void
    {
        $course=Course::create(['academic_code'=>'14020','name'=>'Introdução à Gestão','status'=>'active']);
        Topic::create([
            'course_id'=>$course->id,'source'=>'confirmed_programme','title'=>'Gestão e gestor',
            'status'=>'active','metadata'=>['curricular_number'=>'1.1'],
        ]);
        $url='https://chatgpt.com/g/g-p-abcdef123456/project';
        $this->post('/practice/chatgpt/'.$course->id.'/project',['project_url'=>$url])
            ->assertRedirect('/practice/chatgpt/'.$course->id);
        $this->assertSame($url,DB::table('course_chatgpt_projects')->where('course_id',$course->id)->value('project_url'));
        $this->get('/practice/chatgpt/'.$course->id)->assertOk()
            ->assertSee($url)->assertSee('Preparar atividade no ChatGPT')
            ->assertSee('Registar o estado de conhecimento');
    }

    public function test_unrelated_domains_and_credentials_are_rejected(): void
    {
        $course=Course::create(['academic_code'=>'14020','name'=>'Introdução à Gestão','status'=>'active']);
        foreach ([
            'https://chatgpt.com.evil.example/g/g-p-123/project',
            'https://chatgpt.com@evil.example/g/g-p-123/project',
            'http://chatgpt.com/g/g-p-123/project',
            'https://chatgpt.com/c/123',
        ] as $url) {
            $this->post('/practice/chatgpt/'.$course->id.'/project',['project_url'=>$url])
                ->assertSessionHasErrors('project_url');
        }
        $this->assertDatabaseCount('course_chatgpt_projects',0);
    }

    public function test_navigation_hides_local_materials_without_erasing_data(): void
    {
        $course=Course::create(['academic_code'=>'14020','name'=>'Introdução à Gestão','status'=>'active']);
        $this->get('/courses/'.$course->id)->assertOk()
            ->assertDontSee('Substituir conteúdos da UC')
            ->assertDontSee('Abrir biblioteca da UC')
            ->assertSee('Preparar treino no ChatGPT');
    }
}
