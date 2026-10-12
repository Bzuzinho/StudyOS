<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Topic;
use App\Models\SourceChunk;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Http;

class AiStudyTest extends TestCase
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
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:',
                'prefix' => '', 'foreign_key_constraints' => true],
            'studyos_ai.enabled' => true,
            'studyos_ai.api_key' => 'test-only',
            'studyos_ai.access_code' => 'study-test-code',
            'studyos_ai.monthly_budget_usd' => 10,
        ]);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    private function topic(): Topic
    {
        $course = Course::create(['name' => 'Introdução à Gestão', 'status' => 'active']);
        $topic = Topic::create(['course_id' => $course->id, 'source' => 'manual',
            'title' => 'Funções da gestão', 'status' => 'active', 'taught_at' => now()]);
        $chunk = SourceChunk::create(['course_id' => $course->id, 'ordinal' => 1,
            'content' => str_repeat('Planeamento, organização, direção e controlo são funções de gestão. ', 8),
            'content_hash' => hash('sha256', 'functions'),
            'status' => 'active', 'quality' => 'content', 'title' => 'Aula de Gestão', 'locator' => 'Slide 4']);
        $chunk->topics()->attach($topic->id, ['match_method' => 'manual_material_assignment', 'confidence' => 1]);
        return $topic;
    }

    public function test_authorization_is_required_before_calling_paid_api(): void
    {
        $topic = $this->topic();
        Http::fake();
        $this->post('/ai/topics/'.$topic->id.'/study', ['access_code' => 'wrong'])->assertForbidden();
        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_study_outputs', 0);
    }

    public function test_result_is_source_based_persisted_and_reused_without_new_call(): void
    {
        $topic = $this->topic();
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'summary' => 'A gestão inclui planeamento, organização, direção e controlo.',
                'key_points' => ['Planear', 'Controlar'],
                'questions' => [['question' => 'Quais são as funções?', 'reference_answer' => 'Planear, organizar, dirigir e controlar.', 'source_ids' => [$topic->sourceChunks()->first()->id]]],
                'missing_information' => [],
            ])]]], 'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 120],
        ], 200)]);
        $url = '/ai/topics/'.$topic->id.'/study';
        $this->post($url, ['access_code' => 'study-test-code'])->assertRedirect();
        $this->post($url, ['access_code' => 'study-test-code'])->assertRedirect();
        $this->assertDatabaseCount('ai_study_outputs', 1);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('ai_study_outputs', ['topic_id' => $topic->id, 'input_tokens' => 200]);
    }

    public function test_disabled_provider_never_calls_api(): void
    {
        $topic = $this->topic();
        config(['studyos_ai.enabled' => false]);
        Http::fake();
        $this->post('/ai/topics/'.$topic->id.'/study', ['access_code' => 'study-test-code'])->assertRedirect();
        Http::assertNothingSent();
    }
}
