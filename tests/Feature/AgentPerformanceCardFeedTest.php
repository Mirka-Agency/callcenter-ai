<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Employer\Dashboard\Overview;
use App\Livewire\Employer\Intelligence\Performance;
use App\Livewire\Employer\Intelligence\PerformanceShow;
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

    private function actingAsEmployer(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);
    }
}
