<?php

namespace Tests\Unit;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\DTOs\ReportFilter;
use App\Enums\ReportDatePreset;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Services\Reports\LeadConcernsAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadConcernsAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_concerns_breakdown_groups_by_type(): void
    {
        $organization = Organization::factory()->create();

        ConversationAnalysis::query()->create($this->analysisAttributes($organization->id, [
            'score' => 70,
            'sentiment' => AnalysisSentiment::Neutral,
            'concerns_json' => [
                ['type' => 'price', 'text' => 'a', 'severity' => 'low'],
                ['type' => 'trust', 'text' => 'b', 'severity' => 'medium'],
            ],
        ]));

        $filter = ReportFilter::make($organization->id, ReportDatePreset::Last30);
        $breakdown = app(LeadConcernsAnalytics::class)->concernsByType($filter);

        $this->assertSame(1, collect($breakdown)->firstWhere('type', 'price')['count']);
        $this->assertSame(1, collect($breakdown)->firstWhere('type', 'trust')['count']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function analysisAttributes(int $organizationId, array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $organizationId,
            'source' => ConversationSource::ManualUpload,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 75,
            'summary' => 'خلاصه تست',
            'sentiment' => AnalysisSentiment::Positive,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'analyzed_at' => now(),
        ], $overrides);
    }
}
