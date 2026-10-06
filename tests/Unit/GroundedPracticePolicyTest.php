<?php

namespace Tests\Unit;

use App\Services\Learning\CorpusQualityClassifier;
use PHPUnit\Framework\TestCase;

class GroundedPracticePolicyTest extends TestCase
{
    public function test_a_topic_name_alone_cannot_be_used_as_grounded_answer_content(): void
    {
        $classifier = new CorpusQualityClassifier();

        self::assertSame('outline_only', $classifier->classify('Sistema de Normalização Contabilística (SNC)'));
    }

    public function test_substantive_source_text_can_enter_the_grounded_generator_pipeline(): void
    {
        $source = implode(' ', array_fill(
            0,
            5,
            'O fragmento descreve o conceito, apresenta condições de aplicação, relações com outros elementos e um exemplo suficientemente desenvolvido para servir de referência.',
        ));

        self::assertSame('content', (new CorpusQualityClassifier())->classify($source));
    }
}
