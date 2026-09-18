<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Enums\UserRole;
use App\Livewire\Employer\Dashboard\Overview;
use App\Livewire\Employer\Intelligence\Performance;
use App\Livewire\Employer\Intelligence\PerformanceShow;
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
            ->assertSet('agentCardFilter', 'all')
            ->assertSee('PDF')
            ->assertSee('فیلترها')
            ->assertSee('بازه زمانی')
            ->assertSee('امروز')
            ->assertSee('دیروز')
            ->assertSee('۷ روز گذشته')
            ->assertSee('۳۰ روز گذشته')
            ->assertSee('این ماه')
            ->assertSee('ماه قبل')
            ->assertSee('فصل جاری')
            ->assertSee('سال جاری')
            ->assertSee('بازه دلخواه')
            ->assertDontSeeHtml("showMore ? 'بستن' : 'بیشتر'")
            ->assertDontSee('مشاهده پروفایل کارشناس')
            ->assertDontSee('CSV')
            ->assertDontSee('Excel');
    }

    public function test_individual_performance_page_shows_only_pdf_export(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'Ali',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);

        $this->actingAs($employer);

        Livewire::test(PerformanceShow::class, ['employee' => $employee])
            ->assertSee('PDF')
            ->assertSee('بازه زمانی گزارش')
            ->assertSee('چاپ PDF')
            ->assertSeeHtml('window.print()')
            ->assertDontSeeHtml('onclick="window.print()"')
            ->assertDontSee('مشاهده پروفایل کارشناس')
            ->assertDontSee('CSV')
            ->assertDontSee('Excel')
            ->assertDontSeeHtml('performance.show.export')
            ->assertDontSeeHtml('/export/pdf');
    }

    public function test_individual_performance_print_dialog_prefills_and_applies_existing_date_filters(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'Ali',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);

        $this->actingAs($employer);

        Livewire::test(PerformanceShow::class, ['employee' => $employee])
            ->assertSet('showPrintDateRange', false)
            ->assertSet('printDraftFrom', now()->subDays(29)->toDateString())
            ->assertSet('printDraftTo', now()->toDateString())
            ->call('openPrintDateRange')
            ->assertSet('showPrintDateRange', true)
            ->assertSet('printDraftFrom', now()->subDays(29)->toDateString())
            ->assertSet('printDraftTo', now()->toDateString())
            ->call('applyPrintDateRange', '2026-01-15', '2026-01-01')
            ->assertSet('showPrintDateRange', true)
            ->assertSet('datePreset', 'last_30')
            ->call('applyPrintDateRange', 'not-a-date', '2026-01-15')
            ->assertSet('datePreset', 'last_30')
            ->call('applyPrintDateRange', '2026-01-01', '2026-01-01')
            ->assertSet('showPrintDateRange', false)
            ->assertSet('datePreset', 'custom')
            ->assertSet('customFrom', '2026-01-01')
            ->assertSet('customTo', '2026-01-01')
            ->call('openPrintDateRange')
            ->call('applyPrintDateRange', '2026-01-01', '2026-01-15')
            ->assertSet('customFrom', '2026-01-01')
            ->assertSet('customTo', '2026-01-15')
            ->call('openPrintDateRange')
            ->assertSet('printDraftFrom', '2026-01-01')
            ->assertSet('printDraftTo', '2026-01-15')
            ->call('closePrintDateRange')
            ->assertSet('showPrintDateRange', false);
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

    public function test_performance_page_shows_attention_agents_above_performance_cards(): void
    {
        $organization = $this->actingAsEmployer();
        $attentionAgent = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'رضا',
            'last_name' => 'کریمی',
            'is_active' => true,
        ]);
        $otherAgent = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'سارا',
            'last_name' => 'محمدی',
            'is_active' => true,
        ]);

        $this->seedEmployeeWeaknessAnalyses($organization, $attentionAgent, [
            ['پیگیری ضعیف', 'جمع‌بندی ضعیف'],
            ['پیگیری ضعیف', 'عدم تأیید نیاز'],
            ['جمع‌بندی ضعیف'],
        ]);
        $this->seedEmployeeWeaknessAnalyses($organization, $otherAgent, [
            ['قطع مکالمه'],
            ['توضیح ناقص محصول'],
        ]);

        $html = Livewire::test(Performance::class)->html();
        $attentionPosition = mb_strpos($html, 'data-tour="performance-attention"');
        $cardsPosition = mb_strpos($html, 'data-tour="performance-cards"');

        $this->assertNotFalse($attentionPosition);
        $this->assertNotFalse($cardsPosition);
        $this->assertLessThan($cardsPosition, $attentionPosition);
        $this->assertStringContainsString('کارشناسان نیازمند توجه', $html);
        $this->assertStringContainsString('رضا کریمی', $html);
        $this->assertStringContainsString('پیگیری ضعیف (2)', $html);
        $this->assertStringContainsString('جمع‌بندی ضعیف (2)', $html);
        $this->assertStringNotContainsString('سارا محمدی', explode('data-tour="performance-cards"', $html)[0]);
    }

    private function actingAsEmployer(): Organization
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    /**
     * @param  list<list<string>>  $calls
     */
    private function seedEmployeeWeaknessAnalyses(Organization $organization, OrganizationUser $employee, array $calls): void
    {
        foreach ($calls as $index => $weaknesses) {
            $call = Call::query()->create([
                'organization_id' => $organization->id,
                'organization_user_id' => $employee->id,
                'source' => ConversationSource::Voip,
                'provider_code' => 'novatel',
                'external_call_id' => 'attention-'.$employee->id.'-'.$index,
                'direction' => 'inbound',
                'caller_number' => '091210000'.$index,
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
                'score' => 55,
                'is_evaluable' => true,
                'summary' => 'خلاصه تست',
                'sentiment' => AnalysisSentiment::Neutral,
                'strengths_json' => [],
                'weaknesses_json' => $weaknesses,
                'next_actions_json' => [],
                'lead_quality_json' => ['score' => 50, 'level' => 'medium', 'reason' => 'test'],
                'analyzed_at' => now(),
            ]);
        }
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
