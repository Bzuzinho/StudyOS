<?php

namespace App\Services\Practice;

use App\Models\Exercise;
use App\Models\SourceChunk;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

class GroundedPracticeGenerator
{
    public const GENERATOR_VERSION = 'grounded-recall-v1';

    public function generateAll(int $perTopic = 2): array
    {
        $retired = $this->retireSuperseded();
        $stats = [
            'topics_seen' => 0,
            'eligible_source_chunks' => 0,
            'created' => 0,
            'updated' => 0,
            'retired' => $retired,
        ];

        Topic::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->each(function (Topic $topic) use (&$stats, $perTopic) {
                $result = $this->generateForTopic($topic, $perTopic);
                $stats['topics_seen']++;
                $stats['eligible_source_chunks'] += $result['eligible_source_chunks'];
                $stats['created'] += $result['created'];
                $stats['updated'] += $result['updated'];
            });

        return $stats;
    }

    public function generateForTopic(Topic $topic, int $limit = 2): array
    {
        $chunks = $topic->sourceChunks()
            ->where('source_chunks.status', 'active')
            ->where('source_chunks.quality', 'content')
            ->orderByDesc('source_chunk_topic.confidence')
            ->orderBy('source_chunks.id')
            ->limit($limit)
            ->get();

        $stats = [
            'eligible_source_chunks' => $chunks->count(),
            'created' => 0,
            'updated' => 0,
        ];

        foreach ($chunks as $chunk) {
            DB::transaction(function () use ($topic, $chunk, &$stats) {
                $externalId = hash(
                    'sha256',
                    self::GENERATOR_VERSION.'|topic:'.$topic->id.'|chunk:'.$chunk->id.'|'.$chunk->content_hash,
                );

                $exercise = Exercise::query()
                    ->where('source', 'grounded_generator')
                    ->where('external_id', $externalId)
                    ->first();

                $wasExisting = (bool) $exercise;

                $exercise ??= new Exercise([
                    'source' => 'grounded_generator',
                    'external_id' => $externalId,
                ]);

                $exercise->fill([
                    'course_id' => $topic->course_id,
                    'type' => 'open_text',
                    'title' => 'Evocação · '.$topic->title,
                    'prompt' => 'Sem consultar a fonte, explica o tópico «'.$topic->title.'». Inclui definições, relações, condições e exemplos que consigas recuperar. Só depois compara a tua resposta com a fonte de referência.',
                    'expected_answer' => $chunk->content,
                    'answer_config' => [],
                    'explanation' => 'A referência apresentada pelo StudyOS é o fragmento-fonte recolhido. Não é uma resposta inventada nem uma solução expandida por IA.',
                    'max_points' => 1,
                    'status' => 'active',
                    'metadata' => [
                        'authorship' => 'generated_deterministic',
                        'source_grounded' => true,
                        'generator' => self::GENERATOR_VERSION,
                        'requires_review' => true,
                        'source_quality' => $chunk->quality,
                    ],
                ]);
                $exercise->save();

                $exercise->topics()->syncWithoutDetaching([$topic->id]);
                $exercise->sourceChunks()->syncWithoutDetaching([
                    $chunk->id => ['role' => 'reference'],
                ]);

                $stats[$wasExisting ? 'updated' : 'created']++;
            });
        }

        return $stats;
    }

    public function retireSuperseded(): int
    {
        $query = Exercise::query()
            ->where('source', 'grounded_generator')
            ->where('status', 'active')
            ->whereDoesntHave('sourceChunks', fn ($query) => $query->where('source_chunks.status', 'active'));

        $count = $query->count();

        if ($count > 0) {
            $query->update(['status' => 'superseded']);
        }

        return $count;
    }

    public function eligibleChunkCount(): int
    {
        return SourceChunk::query()->eligibleForPractice()->count();
    }
}
