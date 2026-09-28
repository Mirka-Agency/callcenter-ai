<?php

namespace Tests\Unit;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use PHPUnit\Framework\TestCase;

class AnalyzeAudioJobRetryTest extends TestCase
{
    public function test_analysis_job_retries_transient_failures_and_stays_unique(): void
    {
        $job = new AnalyzeAudioJob(42);

        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 120], $job->backoff());
        $this->assertSame('analyze-call-42', $job->uniqueId());
    }
}
