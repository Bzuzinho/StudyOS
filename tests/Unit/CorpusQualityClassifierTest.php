<?php

namespace Tests\Unit;

use App\Services\Learning\CorpusQualityClassifier;
use PHPUnit\Framework\TestCase;

class CorpusQualityClassifierTest extends TestCase
{
    public function test_outline_only_source_is_not_treated_as_rich_content(): void
    {
        $quality = (new CorpusQualityClassifier())->classify('Fundo de maneio');

        self::assertSame('outline_only', $quality);
    }

    public function test_rich_source_is_eligible_content(): void
    {
        $text = str_repeat(
            'A informação recolhida explica um conceito, as suas relações, condições e aplicação num exemplo académico concreto. ',
            4,
        );

        $quality = (new CorpusQualityClassifier())->classify($text);

        self::assertSame('content', $quality);
    }
}
