<?php

namespace Tests\Unit;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Domain\Voip\Enums\CallStatus;
use App\DTOs\AnalysisListFilter;
use App\Enums\ReportDatePreset;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\AnalysisListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisListQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_by_agent_status_and_duration(): void
    {
        $organization = Organization::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $agentA = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $userA->id,
            'first_name' => 'Ali',
            'last_name' => 'One',
            'is_active' => true,
        ]);
        $agentB = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $userB->id,
            'first_name' => 'Sara',
            'last_name' => 'Two',
            'is_active' => true,
        ]);

        $this->seedAnalysis($organization, $agentA, 'completed', 300, 90);
        $this->seedAnalysis($organization, $agentA, 'missed', 30, 50);
        $this->seedAnalysis($organization, $agentB, 'completed', 600, 80);

        $filter = AnalysisListFilter::make(
            organizationId: $organization->id,
            preset: ReportDatePreset::Last30,
            employeeId: $agentA->id,
            statuses: [CallStatus::Completed->value],
            minDurationSeconds: 120,
        );

        $results = app(AnalysisListQuery::class)->paginate($filter);

        $this->assertSame(1, $results->total());
        $this->assertSame(90, $results->first()->score);
    }

    public function test_overview_resolves_top_agent_with_grouped_aggregate(): void
    {
        $organization = Organization::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $agentA = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $userA->id,
            'first_name' => 'Ali',
            'last_name' => 'Top',
            'is_active' => true,
        ]);
        $agentB = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $userB->id,
            'first_name' => 'Sara',
            'last_name' => 'Second',
            'is_active' => true,
        ]);

        $this->seedAnalysis($organization, $agentA, 'completed', 300, 90);
        $this->seedAnalysis($organization, $agentA, 'completed', 300, 85);
        $this->seedAnalysis($organization, $agentB, 'completed', 300, 80);

        $filter = AnalysisListFilter::make(
            organizationId: $organization->id,
            preset: ReportDatePreset::Last30,
        );

        $overview = app(AnalysisListQuery::class)->overview($filter);

        $this->assertSame('Ali Top', $overview['top_agent_name']);
        $this->assertSame(2, $overview['top_agent_count']);
    }

    private function seedAnalysis(
        Organization $organization,
        OrganizationUser $employee,
        string $status,
        int $durationSeconds,
        int $score,
    ): void {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'status' => $status,
            'processing_status' => 'analyzed',
            'duration_seconds' => $durationSeconds,
            'started_at' => now(),
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => $score,
            'summary' => 'خلاصه تست',
            'sentiment' => AnalysisSentiment::Positive,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'lead_quality_json' => ['score' => 70, 'level' => 'medium', 'reason' => 'test'],
            'analyzed_at' => now(),
        ]);
    }
}
