<?php
namespace Tests\Unit;

use App\Models\Course;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Services\Academic\SlideCurriculumImporter;
use Illuminate\Foundation\Testing\TestCase;

class SlideCurriculumImporterTest extends TestCase
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

    public function test_slide_topics_are_distinct_and_do_not_mark_taught_content(): void
    {
        $course = Course::create(['name' => 'Macroeconomia', 'status' => 'active']);
        $material = Material::create(['course_id' => $course->id, 'source' => 'moodle', 'external_id' => 'ppt:1', 'title' => 'Aula 1', 'status' => 'active']);
        $text = "Produto Interno Bruto\nDefinições e agregados\n\nInflação\nÍndice de preços e exemplos";
        $version = MaterialVersion::create([
            'material_id' => $material->id, 'source_hash' => 'hash-ppt', 'observed_at' => now(),
            'original_filename' => 'Aula1.pptx', 'content_text' => $text,
            'metadata' => ['sections' => [
                ['locator' => 'Slide 1', 'start' => 0, 'length' => mb_strlen("Produto Interno Bruto\nDefinições e agregados")],
                ['locator' => 'Slide 2', 'start' => mb_strpos($text, 'Inflação'), 'length' => mb_strlen("Inflação\nÍndice de preços e exemplos")],
            ]],
        ]);
        $importer = app(SlideCurriculumImporter::class);
        $this->assertSame(2, $importer->import($version)['created']);
        $this->assertSame(0, $importer->import($version)['created']);
        $this->assertSame(2, $course->topics()->where('source', 'moodle_slide')->count());
        $this->assertSame(0, $course->topics()->whereNotNull('taught_at')->count());
    }
}
