<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Employer\Dashboard\Overview;
use App\Livewire\Employer\Intelligence\Performance;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgentPerformanceCardFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_more_performance_cards(): void
    {
        $this->actingAsEmployer();

        Livewire::test(Overview::class)
            ->assertSet('agentCardVisible', 8)
            ->assertSet('agentCardFilter', 'all')
            ->call('loadMoreAgentCards')
            ->assertSet('agentCardVisible', 16)
            ->call('setAgentCardFilter', 'top')
            ->assertSet('agentCardFilter', 'top')
            ->assertSet('agentCardVisible', 8);
    }

    public function test_performance_page_loads_more_cards_and_rejects_unknown_filter(): void
    {
        $this->actingAsEmployer();

        Livewire::test(Performance::class)
            ->call('loadMoreAgentCards')
            ->assertSet('agentCardVisible', 16)
            ->call('setAgentCardFilter', 'invalid')
            ->assertSet('agentCardFilter', 'all');
    }

    public function test_dashboard_shows_team_weaknesses_above_agent_performance_section(): void
    {
        $organization = $this->actingAsEmployer();
        $this->seedWeaknessAnalyses($organization, [
            'جمع‌بندی ضعیف انتهای تماس',
            'جمع‌بندی ضعیف انتهای تماس',
            'عدم تأیید نهایی نیاز مشتری',
        ]);

        $html = Livewire::test(Overview::class)->html();
        $weaknessesPosition = mb_strpos($html, 'ضعف‌های پرتکرار تیم');
        $agentsPosition = mb_strpos($html, 'data-tour="dashboard-agents"');

        $this->assertNotFalse($weaknessesPosition);
        $this->assertNotFalse($agentsPosition);
        $this->assertLessThan($agentsPosition, $weaknessesPosition);
        $this->assertStringContainsString('جمع‌بندی ضعیف انتهای تماس (2)', $html);
        $this->assertStringContainsString('عدم تأیید نهایی نیاز مشتری (1)', $html);

        Livewire::test(Performance::class)
            ->assertSee('ضعف‌های پرتکرار تیم')
            ->assertSee('جمع‌بندی ضعیف انتهای تماس (2)');
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    /** @param  list<string>  $weaknesses */
    private function seedWeaknessAnalyses(Organization $organization, array $weaknesses): void
    {
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'علی',
            'last_name' => 'احمدی',
            'is_active' => true,
        ]);

        foreach ($weaknesses as $index => $weakness) {
            $call = Call::query()->create([
                'organization_id' => $organization->id,
                'organization_user_id' => $employee->id,
                'source' => ConversationSource::Voip,
                'provider_code' => 'novatel',
                'external_call_id' => 'dash-weakness-'.$index,
                'direction' => 'inbound',
                'caller_number' => '0912000000'.$index,
                'receiver_number' => '02100000000',
                'status' => 'completed',
                'processing_status' => 'analyzed',
                'duration_seconds' => 120,
                'started_at' => now()->subDay(),
            ]);

            ConversationAnalysis::query()->create([
                'organization_id' => $organization->id,
                'organization_user_id' => $employee->id,
                'call_id' => $call->id,
                'source' => ConversationSource::Voip,
                'llm_provider' => 'openai',
                'model_name' => 'gpt-4o-mini',
                'score' => 70,
                'is_evaluable' => true,
                'summary' => 'خلاصه تست',
                'sentiment' => AnalysisSentiment::Neutral,
                'strengths_json' => [],
                'weaknesses_json' => [$weakness],
                'next_actions_json' => [],
                'lead_quality_json' => ['score' => 50, 'level' => 'medium', 'reason' => 'test'],
                'analyzed_at' => now(),
            ]);
        }
    }
}
