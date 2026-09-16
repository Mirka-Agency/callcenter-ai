<?php

namespace Tests\Unit;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use PHPUnit\Framework\TestCase;

class AnalyzeAudioJobRetryTest extends TestCase
{
    public function test_analysis_job_is_unique_and_does_not_retry(): void
    {
        $job = new AnalyzeAudioJob(42);

        $this->assertSame(1, $job->tries);
        $this->assertSame('analyze-call-42', $job->uniqueId());
    }
}
