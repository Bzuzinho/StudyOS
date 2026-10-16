<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\Material;
use App\Models\MaterialVersion;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/** Import manuals into canonical active UC topics without changing the curriculum tree. */
class CanonicalManualImporter
{
    public function import(Course $course, string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('O ficheiro não é um ZIP válido.');
        }
        try {
            if ($zip->numFiles > 200 || $zip->getFromName('manifest.json') === false) {
                throw new RuntimeException('Pacote inválido ou com demasiados ficheiros.');
            }
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
            if (($manifest['schema'] ?? null) !== 'studyos.canonical-manuals.v1'
                || (string) ($manifest['course']['academic_code'] ?? '') !== (string) $course->academic_code
                || !is_array($manifest['manuals'] ?? null) || count($manifest['manuals']) === 0) {
                throw new RuntimeException('O pacote não corresponde à UC ou ao formato de manuais.');
            }
            $activeTopics = Curriculum::topics($course->load('topics'))->keyBy(
                fn ($topic) => (string) ($topic->metadata['curricular_number'] ?? '')
            );
            $leaves = Curriculum::leaves($course);
            $entries = [];
            foreach ($manifest['manuals'] as $manual) {
                $number = (string) ($manual['curricular_number'] ?? '');
                $pathMd = $manual['file'] ?? '';
                $pathBlocks = $manual['blocks_file'] ?? '';
                $topic = $activeTopics->get($number);
                if (! $topic || ! $leaves->contains('id', $topic->id)
                    || isset($entries[$number])
                    || !preg_match('/^manuais\/[a-zA-Z0-9_-]+\.md$/D', $pathMd)
                    || !preg_match('/^manuais\/[a-zA-Z0-9_-]+\.blocks\.json$/D', $pathBlocks)) {
                    throw new RuntimeException('Manual associado a um subtema inválido ou duplicado: '.$number);
                }
                $text = $zip->getFromName($pathMd);
                $rawBlocks = $zip->getFromName($pathBlocks);
                if (!is_string($text) || strlen($text) > 400000
                    || !is_string($rawBlocks) || strlen($rawBlocks) > 400000) {
                    throw new RuntimeException('Ficheiros pedagógicos inválidos: '.$number);
                }
                $blocks = app(PedagogicalBlocks::class)->validate(json_decode($rawBlocks, true));
                if (!app(PedagogicalBlocks::class)->forMode($blocks, 'study')
                    || !app(PedagogicalBlocks::class)->forMode($blocks, 'review')
                    || mb_strlen(trim($text)) < 800) {
                    throw new RuntimeException('O manual necessita de matéria e revisão separadas: '.$number);
                }
                $entries[$number] = compact('topic', 'text', 'blocks');
            }
            return DB::transaction(function () use ($course, $entries) {
                foreach ($entries as $entry) {
                    $topic = $entry['topic'];
                    $text = $entry['text'];
                    $blocks = $entry['blocks'];
                    $material = Material::updateOrCreate(
                        ['source' => 'chatgpt_package', 'external_id' => $topic->external_id],
                        ['course_id' => $course->id, 'type' => 'document',
                            'title' => 'Manual de estudo · '.$topic->title, 'status' => 'active',
                            'metadata' => ['study_topic_id' => $topic->id,
                                'pedagogical_origin' => 'studyos_canonical_manual', 'official' => false]]
                    );
                    $version = MaterialVersion::firstOrCreate(
                        ['material_id' => $material->id, 'source_hash' => hash('sha256', $text.'|'.json_encode($blocks))],
                        ['version_label' => 'Manual curricular · '.now()->format('Y-m-d'),
                            'content_text' => $text, 'manual_text' => $text, 'extraction_status' => 'manual_text',
                            'observed_at' => now(), 'metadata' => ['pedagogical_blocks' => $blocks]]
                    );
                    app(CorpusBuilder::class)->rebuildMaterialVersion($version);
                }
                return ['themes' => 0, 'subtopics' => count($entries), 'exercises' => 0];
            });
        } finally {
            $zip->close();
        }
    }
}
