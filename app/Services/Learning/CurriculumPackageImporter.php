<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\Exercise;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class CurriculumPackageImporter
{
    public function import(Course $course, string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('O ficheiro não é um ZIP válido.');
        }
        try {
            if ($zip->numFiles > 100 || $zip->getFromName('manifest.json') === false) {
                throw new RuntimeException('Pacote sem manifesto ou com demasiados ficheiros.');
            }
            $files = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!is_string($name) || str_contains($name, '..') || str_starts_with($name, '/')
                    || str_contains($name, '\\')) {
                    throw new RuntimeException('O pacote contém caminhos inválidos.');
                }
                $stat = $zip->statIndex($i);
                if ((int)($stat['size'] ?? 0) > 400000 || (int)($stat['size'] ?? 0) < 0) {
                    throw new RuntimeException('Ficheiro do pacote demasiado grande.');
                }
                $files[$name] = true;
            }
            $manifest = json_decode((string)$zip->getFromName('manifest.json'), true);
            $exercises = json_decode((string)$zip->getFromName('exercicios.json'), true);
            if (!is_array($manifest) || ($manifest['schema'] ?? '') !== 'studyos.curriculum-package.proposal.v1'
                || (string)($manifest['course']['academic_code'] ?? '') !== (string)$course->academic_code
                || !is_array($manifest['themes'] ?? null)
                || !is_array($exercises['items'] ?? null)) {
                throw new RuntimeException('O manifesto não corresponde à UC ou ao formato suportado.');
            }
            $records = [];
            $paths = [];
            foreach ($manifest['themes'] as $theme) {
                $this->validateNode($theme, $records);
                if (!is_array($theme['children'] ?? null)) {
                    throw new RuntimeException('Tema sem subtemas.');
                }
                foreach ($theme['children'] as $child) {
                    $this->validateNode($child, $records);
                    $name = $child['file'] ?? null;
                    if (!is_string($name) || !preg_match('#^temas/tema-[0-9]{2}/subtema-[0-9]{2}-[0-9]{2}\\.md$#', $name)
                        || !isset($files[$name])) {
                        throw new RuntimeException('Ficheiro de subtema inválido ou em falta.');
                    }
                    $paths[$child['id']] = $name;
                }
            }
            $exerciseIds = [];
            foreach ($exercises['items'] as $item) {
                $kind = $item['type'] ?? 'open';
                if (!in_array($kind, ['open','open_text','true_false','single_answer','numeric'], true)
                    || !is_string($item['id'] ?? null) || mb_strlen($item['id']) > 120
                    || isset($exerciseIds[$item['id']])) {
                    throw new RuntimeException('Tipo ou ID de exercício inválido/repetido.');
                }
                $exerciseIds[$item['id']] = true;
                if ($kind === 'true_false' && !in_array(mb_strtolower($item['reference_answer'] ?? ''), ['verdadeiro','falso'], true)) {
                    throw new RuntimeException('Verdadeiro/falso exige uma solução válida.');
                }
                if ($kind === 'single_answer' && (!isset($item['answer_config']['accepted_answers']) || !is_array($item['answer_config']['accepted_answers']))) {
                    throw new RuntimeException('Resposta curta exige respostas aceites.');
                }
                if ($kind === 'numeric' && !is_numeric(str_replace(',', '.', $item['reference_answer'] ?? ''))) {
                    throw new RuntimeException('Questão numérica exige solução numérica.');
                }
            }
            foreach ($exercises['items'] as $item) {
                if (!is_array($item) || !is_string($item['id'] ?? null)
                    || !is_string($item['topic_id'] ?? null) || !isset($paths[$item['topic_id']])
                    || !is_string($item['prompt'] ?? null) || !is_string($item['reference_answer'] ?? null)
                    || mb_strlen($item['prompt']) > 10000) {
                    throw new RuntimeException('O pacote contém um exercício inválido.');
                }
            }

            return DB::transaction(function () use ($course, $manifest, $exercises, $zip, $paths) {
                $topics = [];
                $created = 0;
                foreach ($manifest['themes'] as $position => $theme) {
                    $parent = $this->upsertTopic($course, $theme, null, $position + 1);
                    $topics[$theme['id']] = $parent;
                    foreach ($theme['children'] as $index => $child) {
                        $topic = $this->upsertTopic($course, $child, $parent->id, $index + 1);
                        $topics[$child['id']] = $topic;
                        $text = (string)$zip->getFromName($paths[$child['id']]);
                        $material = Material::updateOrCreate(
                            ['source' => 'chatgpt_package', 'external_id' => $course->academic_code.':'.$child['id']],
                            ['course_id' => $course->id, 'type' => 'document', 'title' => 'Manual de estudo · '.$child['title'],
                                'status' => 'active', 'metadata' => ['study_topic_id' => $topic->id,
                                    'pedagogical_origin' => 'chatgpt', 'official' => false]]
                        );
                        $hash = hash('sha256', $text);
                        $version = MaterialVersion::firstOrCreate(
                            ['material_id' => $material->id, 'source_hash' => $hash],
                            ['version_label' => 'Pacote ChatGPT · '.now()->format('Y-m-d'),
                                'content_text' => $text, 'manual_text' => $text, 'extraction_status' => 'manual_text',
                                'observed_at' => now(), 'metadata' => ['sections' => [], 'file_extension' => 'md']]
                        );
                        // A later re-import must restore index links even when text is unchanged.
                        app(CorpusBuilder::class)->rebuildMaterialVersion($version);
                        $created++;
                    }
                }
                $exerciseCount = 0;
                foreach ($exercises['items'] as $item) {
                    $exercise = Exercise::updateOrCreate(
                        ['source' => 'chatgpt_package', 'external_id' => $course->academic_code.':'.$item['id']],
                        ['course_id' => $course->id, 'type' => ($item['type'] ?? 'open') === 'open' ? 'open_text' : $item['type'],
                            'answer_config' => $item['answer_config'] ?? null,
                            'max_points' => (float) ($item['max_points'] ?? 1),
                            'title' => 'Exercício · '.$item['id'], 'prompt' => $item['prompt'],
                            'expected_answer' => $item['reference_answer'],
                            'explanation' => $item['explanation'] ?? $item['reference_answer'], 'status' => 'active',
                            'metadata' => ['rubric' => $item['suggested_rubric'] ?? null,
                                'difficulty' => $item['difficulty'] ?? 'medio',
                                'set' => $item['set'] ?? 'geral',
                                'pedagogical_origin' => 'chatgpt']]
                    );
                    $exercise->topics()->syncWithoutDetaching([$topics[$item['topic_id']]->id]);
                    $exerciseCount++;
                }
                return ['themes' => count($manifest['themes']), 'subtopics' => $created,
                    'exercises' => $exerciseCount];
            });
        } finally {
            $zip->close();
        }
    }

    private function validateNode(array $node, array &$records): void
    {
        $id = $node['id'] ?? null;
        if (!is_string($id) || !preg_match('/^IG-[0-9]{2}(\\.[0-9]{2})?$/', $id)
            || !is_string($node['title'] ?? null) || mb_strlen($node['title']) > 240 || isset($records[$id])) {
            throw new RuntimeException('Identificador ou título de tema inválido ou repetido.');
        }
        $records[$id] = true;
    }

    private function upsertTopic(Course $course, array $node, ?int $parentId, int $position): Topic
    {
        $topic = Topic::firstOrNew(['source' => 'chatgpt_package',
            'external_id' => $course->academic_code.':'.$node['id']]);
        $metadata = $topic->metadata ?? [];
        $topic->fill(['course_id' => $course->id, 'parent_id' => $parentId,
            'title' => $node['title'], 'position' => $position, 'status' => 'active',
            'metadata' => [...$metadata, 'curricular_number' => $node['id'],
                'official_fuc' => (bool)($node['official_fuc'] ?? false),
                'pedagogical_origin' => 'chatgpt']]);
        $topic->save();
        return $topic;
    }
}
