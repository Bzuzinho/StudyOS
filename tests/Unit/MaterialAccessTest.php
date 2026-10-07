<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\SourceChunk;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Storage;

class MaterialAccessTest extends TestCase
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
        $this->withoutExceptionHandling();
    }

    public function test_catalog_filters_by_course_origin_and_filename_and_counts_without_loading_chunks(): void
    {
        $course = Course::create(['name' => 'Macroeconomia']);
        $other = Course::create(['name' => 'Contabilidade']);
        [$material, $version] = $this->material($course, 'Módulo 1', 'INTRODUCAO.pdf');
        $this->material($course, 'Material manual', 'introducao.docx', 'manual');
        $this->material($other, 'Outro curso', 'introducao.pdf');
        $this->material($course, 'Sem correspondência', 'capitulo2.pdf');
        $material->replicate()->fill(['title' => 'Retirado', 'status' => 'missing'])->save();
        foreach (['content', 'outline_only'] as $ordinal => $quality) {
            SourceChunk::create([
                'course_id' => $course->id, 'material_version_id' => $version->id,
                'ordinal' => $ordinal, 'content' => 'Conteúdo', 'content_hash' => hash('sha256', $quality),
                'quality' => $quality, 'status' => 'active',
            ]);
        }

        $this->get('/materials?course_id='.$course->id.'&source=moodle&q=introducao')
            ->assertOk()->assertSee('Módulo 1')->assertDontSee('Outro curso')->assertDontSee('Material manual')
            ->assertSee('2 fragmento(s) · 1 apto(s) para prática')
            ->assertViewHas('materials', function ($materials) use ($material) {
                $version = $materials->first()->versions->first();

                return $materials->total() === 1 && $materials->first()->id === $material->id
                    && ! $version->relationLoaded('sourceChunks');
            });
    }

    public function test_pagination_preserves_filters_and_download_links_stay_on_the_current_origin(): void
    {
        $course = Course::create(['name' => 'Macroeconomia']);
        for ($index = 0; $index < 25; $index++) {
            $this->material($course, 'Material '.$index, 'aula'.$index.'.pdf');
        }

        $response = $this->get('/materials?course_id='.$course->id.'&source=moodle')
            ->assertOk()->assertSee('Página 1 de 2')->assertDontSee('http://localhost/materials')
            ->assertViewHas('materials', fn ($materials) => $materials->count() === 24 && $materials->total() === 25);
        $this->assertStringContainsString('href="/materials?', $response->getContent());
        $this->assertStringContainsString('source=moodle&amp;page=2', $response->getContent());
        $this->assertStringContainsString('/versions/', $response->getContent());
        $this->get('/materials?course_id='.$course->id.'&source=moodle&page=2')->assertOk()
            ->assertViewHas('materials', fn ($materials) => $materials->count() === 1);
    }

    public function test_original_file_is_downloadable_even_when_text_extraction_failed(): void
    {
        Storage::fake('local');
        $course = Course::create(['name' => 'Macroeconomia']);
        [$material, $version] = $this->material($course, 'Documento digitalizado', 'aula.pdf');
        $version->update(['extraction_status' => 'failed']);
        Storage::disk('local')->put($version->storage_path, '%PDF-original-test-file');

        $this->get('/materials/'.$material->id.'/versions/'.$version->id.'/download')
            ->assertOk()->assertDownload('aula.pdf')->assertHeader('Content-Type', 'application/pdf')
            ->assertStreamedContent('%PDF-original-test-file');
    }

    public function test_download_cannot_use_a_version_belonging_to_another_material(): void
    {
        $course = Course::create(['name' => 'Macroeconomia']);
        [$material] = $this->material($course, 'Primeiro', 'primeiro.pdf');
        [, $version] = $this->material($course, 'Segundo', 'segundo.pdf');
        $this->withExceptionHandling()->get('/materials/'.$material->id.'/versions/'.$version->id.'/download')->assertNotFound();
    }

    private function material(Course $course, string $title, string $filename, string $source = 'moodle'): array
    {
        $material = Material::create(['course_id' => $course->id, 'source' => $source, 'title' => $title]);
        $version = MaterialVersion::create([
            'material_id' => $material->id, 'source_hash' => hash('sha256', $title), 'observed_at' => now(),
            'storage_disk' => 'local', 'storage_path' => 'materials/'.$material->id.'/'.$filename,
            'original_filename' => $filename, 'mime_type' => 'application/pdf', 'extraction_status' => 'extracted',
        ]);

        return [$material, $version];
    }
}
