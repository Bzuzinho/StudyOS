<?php

namespace App\Services\Learning;

use App\Models\Course;
use App\Models\Exercise;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class CanonicalExerciseImporter
{
    public function import(Course $course, string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('O ZIP não é válido.');
        try {
            if ($zip->numFiles !== 2 || $zip->getFromName('manifest.json') === false) {
                throw new RuntimeException('Pacote de exercícios inválido.');
            }
            $manifest = json_decode((string)$zip->getFromName('manifest.json'), true);
            $payload = json_decode((string)$zip->getFromName('exercicios.json'), true);
            if (($manifest['schema'] ?? '') !== 'studyos.canonical-exercises.v1'
                || (string)($manifest['course']['academic_code'] ?? '') !== (string)$course->academic_code
                || !is_array($payload['items'] ?? null) || count($payload['items']) > 2500) {
                throw new RuntimeException('Manifesto ou exercícios incompatíveis.');
            }
            $topics = Curriculum::leaves($course->load('topics'))->keyBy(fn ($t) => (string)($t->metadata['curricular_number'] ?? ''));
            $seen = [];
            foreach ($payload['items'] as $item) {
                $id = $item['id'] ?? null;
                $number = (string)($item['curricular_number'] ?? '');
                if (!is_string($id) || !preg_match('/^[A-Za-z0-9_.-]{1,120}$/D', $id)
                    || isset($seen[$id]) || !$topics->has($number)
                    || !in_array($item['type'] ?? null, ['numeric','true_false','single_answer','open_text'], true)
                    || !is_string($item['prompt'] ?? null) || trim($item['prompt']) === ''
                    || mb_strlen($item['prompt']) > 10000 || !is_string($item['reference_answer'] ?? null)
                    || !is_string($item['explanation'] ?? null) || trim($item['explanation']) === ''
                    || !in_array($item['difficulty'] ?? null, ['basic','intermediate','advanced'], true)
                    || !is_array($item['answer_config'] ?? [])) {
                    throw new RuntimeException('Exercício inválido: '.(string)$id);
                }
                if (($item['type'] ?? null) === 'numeric' &&
                    (!is_numeric(str_replace(',', '.', $item['reference_answer']))
                    || !is_numeric($item['answer_config']['tolerance'] ?? 0))) {
                    throw new RuntimeException('Solução numérica inválida: '.$id);
                }
                if (($item['type'] ?? null) === 'true_false' &&
                    !in_array(mb_strtolower($item['reference_answer']), ['verdadeiro','falso'], true)) {
                    throw new RuntimeException('Solução V/F inválida: '.$id);
                }
                $seen[$id] = true;
            }
            return DB::transaction(function () use ($course, $payload, $topics) {
                foreach ($payload['items'] as $item) {
                    $exercise = Exercise::updateOrCreate(
                        ['source' => 'chatgpt_package', 'external_id' => $course->academic_code.':'.$item['id']],
                        [
                            'course_id' => $course->id, 'status' => 'active', 'title' => $item['title'] ?? 'Exercício · '.$item['id'],
                            'type' => $item['type'], 'prompt' => $item['prompt'],
                            'expected_answer' => $item['reference_answer'], 'explanation' => $item['explanation'],
                            'answer_config' => $item['answer_config'] ?? null, 'max_points' => 1,
                            'metadata' => ['difficulty' => $item['difficulty'], 'set' => $item['set'] ?? 'geral',
                                'pedagogical_origin' => 'canonical_exercises'],
                        ]
                    );
                    $exercise->topics()->syncWithoutDetaching([$topics[(string)$item['curricular_number']]->id]);
                }
                return ['themes' => 0, 'subtopics' => 0, 'exercises' => count($payload['items'])];
            });
        } finally {
            $zip->close();
        }
    }
}
