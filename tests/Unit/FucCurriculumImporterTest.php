<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\Topic;
use App\Services\Academic\FucCurriculumImporter;
use Illuminate\Foundation\Testing\TestCase;

class FucCurriculumImporterTest extends TestCase
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

    public function test_fuc_programme_imports_hierarchy_without_claiming_anything_taught(): void
    {
        $course = Course::create(['name' => 'Introdução à Gestão', 'academic_code' => '14020', 'status' => 'active']);
        $material = Material::create([
            'course_id' => $course->id, 'source' => 'moodle', 'external_id' => 'fuc:ig',
            'title' => 'Ficha da Unidade Curricular (FUC)', 'status' => 'active',
        ]);
        $version = MaterialVersion::create([
            'material_id' => $material->id, 'source_hash' => 'file-hash-fuc', 'observed_at' => now(),
            'original_filename' => 'Programa_Introducao_Gestao_2026.pdf',
            'content_text' => "1. Identificação da UC\n4. Programa analítico\n1. Fundamentos da gestão das organizações\n1.1. A Gestão e o Gestor\n1.1.1. Conceito de Gestão\n2. Evolução do pensamento em gestão\n2.1. Abordagem Clássica\n5. Metodologias de ensino e aprendizagem\n1. Outras considerações",
        ]);
        $course->topics()->create(['source' => 'source_backed', 'external_id' => 'folder', 'title' => 'MATERIAL DE APOIO PEDAGÓGICO', 'status' => 'active', 'metadata' => ['origin' => 'moodle_section']]);

        $importer = app(FucCurriculumImporter::class);
        $this->assertSame(5, $importer->import($version)['created']);
        $this->assertSame(0, $importer->import($version)['created']);
        $this->assertSame(5, $course->topics()->where('source', 'official_fuc')->count());
        $child = $course->topics()->where('source', 'official_fuc')->where('external_id', $course->id.':1.1.1')->firstOrFail();
        $parent = $course->topics()->whereKey($child->parent_id)->firstOrFail();
        $this->assertSame('A Gestão e o Gestor', $parent->title);
        $this->assertNull($child->taught_at);
        $this->assertSame('archived', $course->topics()->where('external_id', 'folder')->firstOrFail()->status);
    }

    public function test_unrelated_moodle_material_does_not_create_a_syllabus(): void
    {
        $course = Course::create(['name' => 'Macroeconomia', 'status' => 'active']);
        $material = Material::create([
            'course_id' => $course->id, 'source' => 'moodle', 'external_id' => 'slides', 'title' => 'Slides da aula', 'status' => 'active',
        ]);
        $version = MaterialVersion::create([
            'material_id' => $material->id, 'source_hash' => 'slides', 'observed_at' => now(),
            'content_text' => "4. Programa analítico\n1. Um título de slides\n1.1. Tópico da aula\n2. Outro título",
        ]);
        $this->assertFalse(app(FucCurriculumImporter::class)->import($version)['recognized']);
        $this->assertDatabaseCount('topics', 0);
    }
}
