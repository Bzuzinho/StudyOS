<?php

namespace Tests\Unit;

use App\Services\Practice\FreePolynomialGrader;
use PHPUnit\Framework\TestCase;

class FreePolynomialGraderTest extends TestCase
{
    public function test_equivalent_reordered_polynomials(): void
    {
        $result=(new FreePolynomialGrader())->compare('9x^2-10x+2','2+9*x^2-10*x',1);
        $this->assertSame('graded',$result['grading_status']);
        $this->assertSame(100,$result['percentage']);
    }

    public function test_wrong_polynomial(): void
    {
        $result=(new FreePolynomialGrader())->compare('9x^2-10x+2','9x^2-5x+2',1);
        $this->assertSame(0,$result['percentage']);
    }

    public function test_unsupported_expressions_are_not_falsely_failed(): void
    {
        $result=(new FreePolynomialGrader())->compare('sin(x)','cos(x)',1);
        $this->assertSame('pending_review',$result['grading_status']);
        $this->assertNull($result['score']);
    }

    public function test_never_executes_injected_php(): void
    {
        $result=(new FreePolynomialGrader())->compare('x^2','system("id")',1);
        $this->assertSame('pending_review',$result['grading_status']);
    }
}
