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
        $this->assertSame(3, $overview['total']);
        $this->assertSame(3, $overview['total_calls']);
        $this->assertSame(3, $overview['total_leads']);
    }

    public function test_overview_counts_unanalyzed_calls_in_total_calls(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $agent = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'first_name' => 'Ali',
            'last_name' => 'One',
            'is_active' => true,
        ]);

        $this->seedAnalysis($organization, $agent, 'completed', 300, 90);
        Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09121111111',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'pending',
            'duration_seconds' => 120,
            'started_at' => now(),
        ]);

        $overview = app(AnalysisListQuery::class)->overview(AnalysisListFilter::make(
            organizationId: $organization->id,
            preset: ReportDatePreset::Last30,
        ));

        $this->assertSame(1, $overview['total']);
        $this->assertSame(2, $overview['total_calls']);
    }

    public function test_overview_counts_unanalyzed_missed_calls(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $agent = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'first_name' => 'Ali',
            'last_name' => 'One',
            'is_active' => true,
        ]);

        $this->seedAnalysis($organization, $agent, 'completed', 300, 90);
        Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09121111111',
            'receiver_number' => '02100000000',
            'status' => CallStatus::Missed->value,
            'processing_status' => 'pending',
            'duration_seconds' => 0,
            'started_at' => now(),
        ]);
        Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => null,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09122222222',
            'receiver_number' => '101',
            'status' => CallStatus::Missed->value,
            'processing_status' => 'pending',
            'duration_seconds' => 0,
            'started_at' => now(),
        ]);
        Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09123333333',
            'receiver_number' => '102',
            'status' => CallStatus::Busy->value,
            'processing_status' => 'pending',
            'duration_seconds' => 0,
            'started_at' => now(),
        ]);
        Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09124444444',
            'receiver_number' => '103',
            'status' => CallStatus::Cancelled->value,
            'processing_status' => 'pending',
            'duration_seconds' => 0,
            'started_at' => now(),
        ]);

        $overview = app(AnalysisListQuery::class)->overview(AnalysisListFilter::make(
            organizationId: $organization->id,
            preset: ReportDatePreset::Last30,
            assignedEmployeesOnly: true,
        ));

        $this->assertSame(1, $overview['total']);
        $this->assertSame(5, $overview['total_calls']);
        $this->assertSame(4, $overview['missed_count']);
    }

    public function test_overview_call_stats_use_call_date_not_analysis_date(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $agent = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'first_name' => 'Ali',
            'last_name' => 'One',
            'is_active' => true,
        ]);

        // Analyzed today, but the call happened 40 days ago → outside Last30 by call date.
        $oldCall = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000001',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 200,
            'started_at' => now()->subDays(40),
            'conversation_date' => now()->subDays(40),
        ]);
        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'call_id' => $oldCall->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 88,
            'summary' => 'تحلیل امروز برای تماس قدیمی',
            'sentiment' => AnalysisSentiment::Positive,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'lead_quality_json' => ['score' => 80, 'level' => 'high', 'reason' => 'test'],
            'analyzed_at' => now(),
        ]);

        // Call in Last30, never analyzed — must count in total_calls / directions.
        Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'outbound',
            'caller_number' => '02100000000',
            'receiver_number' => '09120000002',
            'status' => 'completed',
            'processing_status' => 'pending',
            'duration_seconds' => 100,
            'started_at' => now()->subDays(2),
        ]);

        // conversation_date in range, started_at outside — still a Last30 call.
        Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000003',
            'receiver_number' => '02100000000',
            'status' => CallStatus::Missed->value,
            'processing_status' => 'pending',
            'duration_seconds' => 0,
            'started_at' => now()->subDays(60),
            'conversation_date' => now()->subDays(1),
        ]);

        $overview = app(AnalysisListQuery::class)->overview(AnalysisListFilter::make(
            organizationId: $organization->id,
            preset: ReportDatePreset::Last30,
        ));

        $this->assertSame(1, $overview['total'], 'analyses use analyzed_at');
        $this->assertSame(2, $overview['total_calls'], 'calls use call occurrence date');
        $this->assertSame(1, $overview['inbound_count']);
        $this->assertSame(1, $overview['outbound_count']);
        $this->assertSame(1, $overview['missed_count']);
        $this->assertSame(100, $overview['average_duration_seconds']);
    }

    public function test_assigned_employees_only_excludes_unassigned_analyses(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $agent = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'first_name' => 'Ali',
            'last_name' => 'Assigned',
            'is_active' => true,
        ]);

        $this->seedAnalysis($organization, $agent, 'completed', 300, 90);
        $this->seedUnassignedAnalysis($organization);

        $all = app(AnalysisListQuery::class)->paginate(AnalysisListFilter::make(
            organizationId: $organization->id,
            preset: ReportDatePreset::Last30,
        ));
        $assigned = app(AnalysisListQuery::class)->paginate(AnalysisListFilter::make(
            organizationId: $organization->id,
            preset: ReportDatePreset::Last30,
            assignedEmployeesOnly: true,
        ));

        $this->assertSame(2, $all->total());
        $this->assertSame(1, $assigned->total());
        $this->assertSame($agent->id, $assigned->first()->organization_user_id);
    }

    public function test_filters_calls_that_need_attention(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $agent = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'first_name' => 'Ali',
            'last_name' => 'One',
            'is_active' => true,
        ]);

        $this->seedAnalysis($organization, $agent, 'completed', 300, 90);
        $attention = $this->seedAnalysis($organization, $agent, 'completed', 240, 40, true);

        $results = app(AnalysisListQuery::class)->paginate(AnalysisListFilter::make(
            organizationId: $organization->id,
            preset: ReportDatePreset::Last30,
            needsAttention: true,
        ));

        $this->assertSame(1, $results->total());
        $this->assertTrue($results->first()->needs_attention);
        $this->assertSame($attention->id, $results->first()->id);
    }

    private function seedUnassignedAnalysis(Organization $organization): void
    {
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => null,
            'source' => ConversationSource::Voip,
            'provider_code' => 'novatel',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09123333333',
            'receiver_number' => '101',
            'status' => 'completed',
            'processing_status' => 'analyzed',
            'duration_seconds' => 60,
            'started_at' => now(),
        ]);

        ConversationAnalysis::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => null,
            'call_id' => $call->id,
            'source' => ConversationSource::Voip,
            'llm_provider' => 'openai',
            'model_name' => 'gpt-4o-mini',
            'score' => 10,
            'summary' => 'بدون کارشناس',
            'sentiment' => AnalysisSentiment::Neutral,
            'strengths_json' => [],
            'weaknesses_json' => [],
            'next_actions_json' => [],
            'analyzed_at' => now(),
        ]);
    }

    private function seedAnalysis(
        Organization $organization,
        OrganizationUser $employee,
        string $status,
        int $durationSeconds,
        int $score,
        bool $needsAttention = false,
    ): ConversationAnalysis {
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

        return ConversationAnalysis::query()->create([
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
            'needs_attention' => $needsAttention,
            'attention_json' => $needsAttention ? [
                'needed' => true,
                'categories' => ['agent'],
                'reason' => 'مشتری به عملکرد کارشناس اعتراض دارد',
            ] : null,
            'analyzed_at' => now(),
        ]);
    }
}
