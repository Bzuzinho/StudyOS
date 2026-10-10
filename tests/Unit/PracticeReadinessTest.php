<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Topic;
use Illuminate\Foundation\Testing\TestCase;

class PracticeReadinessTest extends TestCase
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

    public function test_diagnostic_shows_every_uc_without_assuming_competence(): void
    {
        $empty = Course::create(['name' => 'Inglês', 'status' => 'active']);
        $course = Course::create(['name' => 'Macroeconomia', 'status' => 'active']);
        Topic::create(['course_id' => $course->id, 'source' => 'manual', 'title' => 'Produto interno bruto', 'status' => 'active', 'taught_at' => now()]);
        Topic::create(['course_id' => $course->id, 'source' => 'manual', 'title' => 'Inflação', 'status' => 'active']);
        $this->get('/practice')->assertOk()->assertSee('Diagnóstico inicial por UC')
            ->assertSee('Inglês')->assertSee('Macroeconomia')
            ->assertViewHas('diagnostics', fn ($items) =>
                $items->firstWhere('course.id', $course->id)['needs_diagnosis'] === 1
                && $items->firstWhere('course.id', $empty->id)['topics'] === 0
            );
    }
}
