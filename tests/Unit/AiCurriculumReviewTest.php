<?php
namespace Tests\Unit;

use App\Models\Course;
use App\Models\LessonSummary;
use App\Models\SourceChunk;
use App\Models\Topic;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Http;

class AiCurriculumReviewTest extends TestCase
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
            'studyos_ai.enabled' => true, 'studyos_ai.api_key' => 'dummy',
            'studyos_ai.access_code' => 'authorized', 'studyos_ai.monthly_budget_usd' => 10,
        ]);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_curfew_for_unapproved_user_and_no_mutation_of_taught_status(): void
    {
        $course = Course::create(['name' => 'Introdução à Gestão', 'status' => 'active']);
        $topic = Topic::create(['course_id' => $course->id, 'source' => 'official_fuc',
            'title' => 'Eficiência e eficácia', 'status' => 'active']);
        $summary = LessonSummary::create(['course_id' => $course->id, 'source' => 'manual',
            'title' => 'Aula de gestão', 'content' => 'Estudámos a distinção entre eficiência e eficácia.']);
        $chunk = SourceChunk::create(['course_id' => $course->id, 'ordinal' => 1,
            'content' => str_repeat('Eficiência consiste em usar os recursos adequadamente. ', 5),
            'content_hash' => hash('sha256', 'curriculum'),
            'status' => 'active', 'quality' => 'content', 'locator' => 'Slide 3', 'title' => 'Gestão']);
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'likely_taught' => [
                    ['topic_id' => $topic->id, 'summary_ids' => [$summary->id], 'reason' => 'Mencionado no sumário'],
                    ['topic_id' => 9999, 'summary_ids' => [$summary->id], 'reason' => 'Id desconhecido'],
                ],
                'missing_topics' => [
                    ['title' => 'Conceitos complementares', 'chunk_ids' => [$chunk->id], 'reason' => 'Surge nos slides'],
                    ['title' => 'Falso sem fonte', 'chunk_ids' => [9999], 'reason' => 'Inventado'],
                ],
            ])]]], 'usage' => ['prompt_tokens' => 400, 'completion_tokens' => 150],
        ], 200)]);
        $url = '/ai/courses/'.$course->id.'/review';
        $this->post($url, ['access_code' => 'wrong'])->assertForbidden();
        Http::assertNothingSent();
        $this->post($url, ['access_code' => 'authorized'])->assertRedirect();
        $this->post($url, ['access_code' => 'authorized'])->assertRedirect();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('ai_curriculum_reviews', 1);
        $this->assertNull($topic->fresh()->taught_at);
        $result = json_decode(\Illuminate\Support\Facades\DB::table('ai_curriculum_reviews')->first()->output, true);
        $this->assertCount(1, $result['likely_taught']);
        $this->assertCount(1, $result['missing_topics']);
    }
}
