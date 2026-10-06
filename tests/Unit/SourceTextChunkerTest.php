<?php

namespace Tests\Unit;

use App\Services\Learning\SourceTextChunker;
use PHPUnit\Framework\TestCase;

class SourceTextChunkerTest extends TestCase
{
    public function test_short_line_outline_is_kept_as_separate_fragments(): void
    {
        $chunks = (new SourceTextChunker())->chunk(
            "Contabilidade, balanço e resultados\nSistema de Normalização Contabilística (SNC)\nEstrutura conceptual\nInformação financeira",
        );

        self::assertCount(4, $chunks);
        self::assertSame('Estrutura conceptual', $chunks[2]);
    }

    public function test_paragraphs_are_combined_without_exceeding_target_when_possible(): void
    {
        $chunks = (new SourceTextChunker())->chunk(
            "Primeiro parágrafo com informação suficientemente curta.\n\nSegundo parágrafo relacionado com o primeiro.",
            140,
        );

        self::assertCount(1, $chunks);
        self::assertStringContainsString('Segundo parágrafo', $chunks[0]);
    }

    public function test_empty_text_returns_no_chunks(): void
    {
        self::assertSame([], (new SourceTextChunker())->chunk("  \n "));
    }
}
