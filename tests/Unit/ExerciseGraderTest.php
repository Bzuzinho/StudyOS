<?php

namespace Tests\Unit;

use App\Services\Practice\ExerciseGrader;
use PHPUnit\Framework\TestCase;

class ExerciseGraderTest extends TestCase
{
    public function test_single_answer_is_normalized_before_comparison(): void
    {
        $result = (new ExerciseGrader())->grade(
            'single_answer',
            'Ativo = Passivo + Capital Próprio',
            [],
            '  ativo = passivo + capital próprio ',
            2,
        );

        self::assertSame('graded', $result['grading_status']);
        self::assertSame(2.0, $result['score']);
        self::assertSame(100.0, $result['percentage']);
    }

    public function test_numeric_answer_accepts_configured_tolerance(): void
    {
        $result = (new ExerciseGrader())->grade(
            'numeric',
            '10,5',
            ['tolerance' => 0.1],
            '10.58',
            1,
        );

        self::assertSame(100.0, $result['percentage']);
    }

    public function test_true_false_accepts_portuguese_values(): void
    {
        $result = (new ExerciseGrader())->grade('true_false', 'verdadeiro', [], 'V', 1);

        self::assertSame(100.0, $result['percentage']);
    }

    public function test_open_text_waits_for_review(): void
    {
        $result = (new ExerciseGrader())->grade('open_text', null, [], 'Resposta livre', 4);

        self::assertSame('pending_review', $result['grading_status']);
        self::assertNull($result['score']);
        self::assertNull($result['percentage']);
    }
}
