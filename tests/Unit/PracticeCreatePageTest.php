<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\SourceChunk;
use Illuminate\Foundation\Testing\TestCase;

class PracticeCreatePageTest extends TestCase
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

    public function test_create_exercise_renders_with_an_existing_source_excerpt(): void
    {
        $course = Course::create(['name' => 'Macroeconomia', 'status' => 'active']);
        SourceChunk::create([
            'course_id' => $course->id,
            'ordinal' => 1, 'content' => 'Definição detalhada de produto interno bruto e seus componentes.',
            'content_hash' => hash('sha256', 'PIB'),
            'quality' => 'content', 'status' => 'active',
            'title' => 'Contas nacionais', 'locator' => 'Página 1',
        ]);
        $this->get('/practice/create?course_id='.$course->id)
            ->assertOk()->assertSee('Contas nacionais')->assertSee('Página 1');
    }
}
