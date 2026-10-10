<?php

namespace Tests\Unit;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\Topic;
use App\Services\Learning\StudyRecommendationService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;

class StudyRecommendationTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'UTC'));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_confirmed_taught_topics_are_recommended(): void
    {
        $course = Course::create(['name' => 'Macroeconomia', 'status' => 'active', 'semester' => 1]);
        $taught = Topic::create(['course_id' => $course->id, 'source' => 'manual', 'title' => 'Inflação', 'status' => 'active', 'taught_at' => now()]);
        Topic::create(['course_id' => $course->id, 'source' => 'manual', 'title' => 'PIB', 'status' => 'active']);
        $plan = app(StudyRecommendationService::class)->forCourse($course);
        $this->assertCount(1, $plan['recommendations']);
        $this->assertSame($taught->id, $plan['recommendations'][0]['topic']->id);
        $this->assertSame('study', $plan['recommendations'][0]['action']);
        $this->get('/study')->assertOk()->assertSee('Plano de estudo e treino por UC')->assertSee('Inflação');
    }

    public function test_upcoming_nonconditional_assessment_is_selected(): void
    {
        $course = Course::create(['name' => 'Gestão', 'status' => 'active', 'semester' => 1]);
        Assessment::create(['course_id' => $course->id, 'source' => 'manual', 'external_id' => 'test1', 'title' => 'Prova condicional', 'type' => 'test', 'due_at' => '2026-10-11 12:00:00', 'metadata' => ['conditional' => true]]);
        Assessment::create(['course_id' => $course->id, 'source' => 'manual', 'external_id' => 'test2', 'title' => 'Primeiro teste', 'type' => 'test', 'due_at' => '2026-10-12 12:00:00']);
        $plan = app(StudyRecommendationService::class)->forCourse($course);
        $this->assertSame('Primeiro teste', $plan['assessment']->title);
        $this->assertSame(2, $plan['days_to_assessment']);
    }
}
