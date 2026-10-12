<?php
namespace Tests\Unit;
use App\Models\Course;
use App\Models\Topic;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\SourceChunk;
use App\Services\Learning\TopicCoverageService;
use App\Services\Learning\CorpusBuilder;
use Illuminate\Foundation\Testing\TestCase;

class TopicCoverageTest extends TestCase
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
            'session.driver' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_topic_document_coverage_distinguishes_missing_partial_and_study_ready_evidence(): void
    {
        $course = Course::create(['name' => 'Gestão', 'status' => 'active']);
        $topic = Topic::create(['course_id' => $course->id, 'source' => 'manual', 'title' => 'Planeamento', 'status' => 'active', 'taught_at' => now()]);
        $service = app(TopicCoverageService::class);
        $this->assertSame('sem_documentacao', $service->assess($topic)['status']);
        $chunk = SourceChunk::create(['course_id' => $course->id, 'ordinal' => 1, 'content' => 'Breve resumo de planeamento.',
            'content_hash' => hash('sha256', 'short'), 'quality' => 'outline_only', 'status' => 'active']);
        $chunk->topics()->attach($topic->id, ['match_method' => 'manual_material_assignment', 'confidence' => 1.0]);
        $this->assertSame('precisa_complemento', $service->assess($topic)['status']);
        $chunk->update(['content' => implode(' ', array_fill(0, 360, 'conceito'))]);
        $this->assertSame('suficiente_para_estudar', $service->assess($topic)['status']);
    }

    public function test_manually_added_material_is_explicitly_linked_only_to_its_course_topic(): void
    {
        $course = Course::create(['name' => 'Gestão', 'status' => 'active']);
        $other = Course::create(['name' => 'Inglês', 'status' => 'active']);
        $topic = Topic::create(['course_id' => $course->id, 'source' => 'manual', 'title' => 'Planeamento', 'status' => 'active']);
        $foreign = Topic::create(['course_id' => $other->id, 'source' => 'manual', 'title' => 'Grammar', 'status' => 'active']);
        $this->postJson('/materials', ['course_id' => $course->id, 'topic_id' => $foreign->id, 'type' => 'note', 'title' => 'Notas', 'content_text' => str_repeat('Estudo e aplicação de conceitos. ', 14)])->assertUnprocessable();
        $this->post('/materials', ['course_id' => $course->id, 'topic_id' => $topic->id, 'type' => 'note', 'title' => 'Apontamentos sobre planeamento', 'content_text' => str_repeat('Planeamento e aplicação de conceitos. ', 14)])->assertRedirect();
        $material = Material::where('title', 'Apontamentos sobre planeamento')->firstOrFail();
        $this->assertSame($topic->id, $material->metadata['study_topic_id']);
        $this->assertGreaterThan(0, $topic->sourceChunks()->count());
        $this->assertSame(0, $foreign->sourceChunks()->count());
    }
}
