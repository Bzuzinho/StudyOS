<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\LessonSummary;
use App\Models\Material;
use App\Services\Academic\SourceBackedTopicBuilder;
use Illuminate\Foundation\Testing\TestCase;

class SourceBackedTopicBuilderTest extends TestCase
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
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
        ]);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_observed_sections_produce_idempotent_provisional_topics_without_fabricating_syllabus(): void
    {
        $course = Course::create(['name' => 'Macroeconomia', 'status' => 'active']);
        Material::create([
            'course_id' => $course->id, 'source' => 'moodle',
            'external_id' => 'moodle:123', 'title' => 'Slides',
            'status' => 'active',
            'metadata' => ['moodle_section_name' => 'Contas Nacionais e Produto Interno Bruto'],
        ]);
        Material::create([
            'course_id' => $course->id, 'source' => 'moodle',
            'external_id' => 'moodle:124', 'title' => 'Bibliografia',
            'status' => 'active',
            'metadata' => ['moodle_section_name' => 'Geral'],
        ]);
        $builder = app(SourceBackedTopicBuilder::class);
        $this->assertSame(1, $builder->build($course)['created']);
        $this->assertSame(0, $builder->build($course)['created']);
        $topic = $course->topics()->firstOrFail();
        $this->assertSame('Contas Nacionais e Produto Interno Bruto', $topic->title);
        $this->assertTrue($topic->metadata['provisional']);
        $this->assertFalse($topic->metadata['official_syllabus_verified']);
        $this->assertNull($topic->taught_at);
    }

    public function test_actual_lesson_summary_lines_can_populate_a_uc_without_claiming_taught_progress(): void
    {
        $course = Course::create(['name' => 'Estatística', 'status' => 'active']);
        LessonSummary::create([
            'course_id' => $course->id, 'source' => 'inforestudante_audit',
            'external_id' => 'summary-123', 'title' => 'Sumário',
            'content' => "Distribuição de frequências\nMedidas de tendência central",
        ]);
        $result = app(SourceBackedTopicBuilder::class)->build($course);
        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $course->topics()->whereNotNull('taught_at')->count());
    }
}
