<?php

namespace Tests\Unit;

use App\Jobs\ExtractMaterialVersion;
use PHPUnit\Framework\TestCase;

class ExtractMaterialVersionJobTest extends TestCase
{
    public function test_job_has_bounded_retry_and_timeout_policy(): void
    {
        $job = new ExtractMaterialVersion(42);

        self::assertSame(42, $job->materialVersionId);
        self::assertSame(2, $job->tries);
        self::assertSame(180, $job->timeout);
        self::assertTrue($job->failOnTimeout);
        self::assertSame([10, 30], $job->backoff());
    }
}
