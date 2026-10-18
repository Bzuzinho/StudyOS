<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Topic;
use Illuminate\Foundation\Testing\TestCase;

class StudyMaterialRequestTest extends TestCase
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

    public function test_course_offers_real_material_entrypoints_without_technical_diagnostics(): void
    {
        $course = Course::create(['name' => 'Introdução à Gestão', 'status' => 'active']);
        Topic::create(['course_id' => $course->id, 'source' => 'manual', 'title' => 'Abordagem Clássica', 'status' => 'active', 'taught_at' => now()]);
        Topic::create(['course_id' => $course->id, 'source' => 'manual', 'title' => 'Planeamento Estratégico', 'status' => 'active']);

        $this->get('/courses/'.$course->id)->assertOk()
            ->assertDontSee('Abrir biblioteca da UC')->assertSee('Preparar treino no ChatGPT')
            ->assertSee('Abordagem Clássica')
            ->assertDontSee('excertos indexados')->assertDontSee('Fonte auditada');
    }

    public function test_more_materials_form_preselects_the_correct_course(): void
    {
        $course = Course::create(['name' => 'Introdução à Gestão', 'status' => 'active']);
        $this->get('/materials/create?course_id='.$course->id)->assertOk()
            ->assertSee('Adicionar material')
            ->assertSee('value="'.$course->id.'" selected', false);
    }
}
