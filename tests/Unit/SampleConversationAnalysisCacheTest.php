<?php

namespace Tests\Unit;

use App\Support\SampleConversationAnalysisCache;
use App\Support\SampleConversations;
use Tests\TestCase;

class SampleConversationAnalysisCacheTest extends TestCase
{
    public function test_both_sample_conversations_have_cached_analysis_payloads(): void
    {
        foreach (SampleConversations::all() as $sample) {
            $this->assertTrue(
                SampleConversationAnalysisCache::has($sample['id']),
                "Missing cached analysis for sample [{$sample['id']}].",
            );

            $payload = SampleConversationAnalysisCache::get($sample['id']);

            $this->assertArrayHasKey('summary', $payload);
            $this->assertArrayHasKey('score', $payload);
            $this->assertArrayHasKey('performance_dimensions', $payload);
        }
    }
}
