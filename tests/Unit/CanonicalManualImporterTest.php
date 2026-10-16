<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Services\Academic\ConfirmedCurriculum;
use App\Services\Learning\CanonicalManualImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class CanonicalManualImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_import_preserves_confirmed_curriculum_and_opens_reader(): void
    {
        $course = Course::create(['academic_code' => '6709', 'name' => 'Contabilidade Financeira I', 'status' => 'active']);
        app(ConfirmedCurriculum::class)->apply($course);
        $topic = $course->topics()->where('source', 'confirmed_programme')
            ->where('metadata->curricular_number', '1.1')->firstOrFail();
        $ids = $course->topics()->pluck('id')->all();
        $file = tempnam(sys_get_temp_dir(), 'manual');
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($file, ZipArchive::OVERWRITE) === true);
        $zip->addFromString('manifest.json', json_encode([
            'schema' => 'studyos.canonical-manuals.v1',
            'course' => ['academic_code' => '6709'],
            'manuals' => [['curricular_number' => '1.1',
                'file' => 'manuais/1-1.md', 'blocks_file' => 'manuais/1-1.blocks.json']],
        ]));
        $zip->addFromString('manuais/1-1.md', '# A Contabilidade'.str_repeat(' Explicação contabilística fundamentada.', 35));
        $zip->addFromString('manuais/1-1.blocks.json', json_encode([
            'schema' => 'studyos.pedagogical-blocks.v1', 'blocks' => [
                ['type' => 'explanation', 'mode' => 'study', 'title' => 'Teoria', 'body' => 'Reconhecimento e mensuração.'],
                ['type' => 'summary', 'mode' => 'review', 'title' => 'Revisão', 'body' => 'Síntese.'],
            ],
        ]));
        $zip->close();
        try {
            $result = app(CanonicalManualImporter::class)->import($course, $file);
            $this->assertSame(1, $result['subtopics']);
            $this->assertSame($ids, $course->topics()->pluck('id')->all());
            $this->get('/courses/'.$course->id.'/package-topics/'.$topic->id)->assertOk()->assertSee('Teoria');
            $this->get('/courses/'.$course->id.'/package-topics/'.$topic->id.'?mode=review')->assertOk()->assertSee('Síntese');
        } finally {
            @unlink($file);
        }
    }
}
