<?php

namespace Tests\Unit;

use App\Services\Practice\MasteryCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class MasteryCalculatorTest extends TestCase
{
    public function test_no_evidence_does_not_create_a_score(): void
    {
        $result = (new MasteryCalculator())->calculate([], 0, null);

        self::assertSame('no_evidence', $result['status']);
        self::assertNull($result['score_percent']);
    }

    public function test_less_than_three_distinct_exercises_is_insufficient_evidence(): void
    {
        $result = (new MasteryCalculator())->calculate(
            [1 => 100, 2 => 100],
            6,
            new DateTimeImmutable(),
        );

        self::assertSame('insufficient_evidence', $result['status']);
        self::assertSame(100.0, $result['score_percent']);
        self::assertSame(2, $result['evidence_exercises']);
    }

    public function test_three_distinct_exercises_can_support_a_level(): void
    {
        $result = (new MasteryCalculator())->calculate(
            [1 => 80, 2 => 90, 3 => 85],
            4,
            new DateTimeImmutable(),
        );

        self::assertSame('strong', $result['status']);
        self::assertSame(85.0, $result['score_percent']);
        self::assertSame(3, $result['evidence_exercises']);
    }
}
