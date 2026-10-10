<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Topic;
use Illuminate\Foundation\Testing\TestCase;

class StudyReadinessTest extends TestCase
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

    public function test_empty_active_courses_are_visible_in_study_and_diagnosis(): void
    {
        $course = Course::create(['name' => 'Macroeconomia', 'status' => 'active', 'semester' => 1]);
        $this->get('/study')->assertOk()->assertSee('Macroeconomia')->assertSee('Programa por estruturar');
        $this->get('/courses/'.$course->id)->assertOk()->assertSee('Programa ainda não estruturado');
        $this->get('/courses')->assertOk()->assertSee('Programa por estruturar');
    }

    public function test_study_time_is_only_counted_after_explicit_completion(): void
    {
        $course = Course::create(['name' => 'Inglês', 'status' => 'active', 'semester' => 1]);
        $this->post('/study', [
            'course_id' => $course->id,
            'type' => 'study',
            'date' => '2026-10-12',
            'start_time' => '18:00',
            'planned_minutes' => 60,
        ])->assertRedirect('/study');

        $session = $course->studySessions()->firstOrFail();
        $this->get('/study')->assertOk()->assertSee('minutos de estudo confirmados')
            ->assertViewHas('completedMinutes', 0);

        $this->patch('/study/'.$session->id.'/complete', ['actual_minutes' => 45])->assertRedirect('/study');
        $this->assertSame('completed', $session->fresh()->status);
        $this->assertSame(45, $session->fresh()->metadata['actual_minutes']);
        $this->get('/study')->assertOk()->assertViewHas('completedMinutes', 45)
            ->assertViewHas('plannedMinutes', 0);

        $this->withExceptionHandling()->patchJson('/study/'.$session->id.'/complete', ['actual_minutes' => 0])->assertUnprocessable();
        $this->assertSame(45, $session->fresh()->metadata['actual_minutes']);
    }
    public function test_only_taught_topics_can_be_selected_when_planning_study(): void
    {
        $course = Course::create(['name' => 'Macroeconomia', 'status' => 'active', 'semester' => 1]);
        $taught = Topic::create(['course_id' => $course->id, 'source' => 'manual', 'title' => 'Inflação', 'status' => 'active', 'taught_at' => now()]);
        $untaught = Topic::create(['course_id' => $course->id, 'source' => 'manual', 'title' => 'PIB por lecionar', 'status' => 'active']);

        $this->get('/study/create?course_id='.$course->id)->assertOk()
            ->assertSee('Inflação')->assertDontSee('PIB por lecionar')
            ->assertSee('Selecionar tudo')->assertSee('Desselecionar tudo')
            ->assertSee('action="/study"', false);

        $payload = [
            'course_id' => $course->id, 'type' => 'study',
            'date' => '2026-10-12', 'start_time' => '18:00', 'planned_minutes' => '60',
        ];

        $this->withExceptionHandling()->postJson('/study', [
            ...$payload, 'topic_ids' => [$untaught->id],
        ])->assertUnprocessable();

        $this->post('/study', [...$payload, 'topic_ids' => [$taught->id]])->assertRedirect('/study');
        $session = $course->studySessions()->firstOrFail();
        $this->assertSame([$taught->id], $session->topics()->pluck('topics.id')->all());
        $this->assertSame(60, (int) $session->planned_minutes);
        $this->get('/study')->assertOk();
    }

}
