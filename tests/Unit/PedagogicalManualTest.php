<?php

namespace Tests\Unit;

use App\Services\Learning\PedagogicalManual;
use PHPUnit\Framework\TestCase;

class PedagogicalManualTest extends TestCase
{
    public function test_separates_practice_sections_from_manual(): void
    {
        $manual = new PedagogicalManual();
        $sections = $manual->sections("## Desenvolvimento teórico\nTexto explicativo.\n\n## Síntese para revisão\nIdeia essencial.\n\n## Exercício de revisão — desenvolvimento\nPergunta com resposta.\n\n## Exercício de aplicação\nEnunciado.");
        self::assertCount(2, $sections);
        self::assertSame('theory', $sections[0]['kind']);
        self::assertSame('summary', $sections[1]['kind']);
        self::assertCount(1, $manual->review($sections));
    }

    public function test_review_is_not_made_up_from_theory(): void
    {
        $manual = new PedagogicalManual();
        self::assertSame([], $manual->review($manual->sections("## Desenvolvimento teórico\nExplicação longa.")));
    }
}
