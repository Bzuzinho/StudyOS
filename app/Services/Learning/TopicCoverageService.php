<?php

namespace App\Services\Learning;

use App\Models\Topic;

class TopicCoverageService
{
    /**
     * A conservative document-volume estimate, not a claim of conceptual
     * completeness or a measurement of the student's understanding.
     */
    public function assess(Topic $topic): array
    {
        $chunks = $topic->sourceChunks()
            ->where('source_chunks.status', 'active')
            ->where('source_chunks.course_id', $topic->course_id)
            ->get(['source_chunks.id', 'source_chunks.content', 'source_chunks.material_version_id', 'source_chunks.lesson_summary_id']);

        $words = 0;
        $substantial = 0;
        $sources = [];
        foreach ($chunks as $chunk) {
            $count = str_word_count(strip_tags((string) $chunk->content), 0, 'ÀÁÂÃÇÉÊÍÓÔÕÚÜàáâãçéêíóôõúü');
            $words += $count;
            if ($count >= 40) {
                $substantial++;
            }
            $sources[($chunk->material_version_id ? 'm:' : 's:').($chunk->material_version_id ?? $chunk->lesson_summary_id ?? $chunk->id)] = true;
        }

        $status = $chunks->isEmpty()
            ? 'sem_documentacao'
            : (($words >= 200 && $substantial >= 2) || $words >= 350
                ? 'suficiente_para_estudar'
                : 'precisa_complemento');

        return [
            'status' => $status,
            'fragments' => $chunks->count(),
            'words' => $words,
            'sources' => count($sources),
        ];
    }
}
