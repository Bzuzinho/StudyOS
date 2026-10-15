<?php

namespace Tests\Unit;

use App\Services\Learning\PedagogicalBlocks;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PedagogicalBlocksTest extends TestCase
{
    public function test_blocks_are_validated_and_separated_by_mode(): void
    {
        $service = new PedagogicalBlocks();
        $blocks = $service->validate(['schema'=>'studyos.pedagogical-blocks.v1','blocks'=>[
            ['type'=>'definition','mode'=>'study','title'=>'Definição','body'=>'Texto'],
            ['type'=>'summary','mode'=>'review','title'=>'Síntese','body'=>'Resumo'],
            ['type'=>'key_idea','mode'=>'both','title'=>'Ideia','body'=>'Essencial'],
        ]]);
        self::assertCount(2, $service->forMode($blocks, 'study'));
        self::assertCount(2, $service->forMode($blocks, 'review'));
        self::assertSame('Síntese', $service->forMode($blocks, 'review')[0]['title']);
    }

    public function test_exercise_block_cannot_be_embedded_in_manual(): void
    {
        $this->expectException(RuntimeException::class);
        (new PedagogicalBlocks())->validate(['schema'=>'studyos.pedagogical-blocks.v1','blocks'=>[
            ['type'=>'exercise','mode'=>'study','title'=>'Pergunta','body'=>'Questão'],
        ]]);
    }

    public function test_diagram_requires_steps(): void
    {
        $this->expectException(RuntimeException::class);
        (new PedagogicalBlocks())->validate(['schema'=>'studyos.pedagogical-blocks.v1','blocks'=>[
            ['type'=>'process','mode'=>'study','title'=>'Fluxo','body'=>'Explicação'],
        ]]);
    }
}
