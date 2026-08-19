<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Employer\Dashboard\Overview;
use App\Livewire\Employer\Intelligence\Performance;
use App\Models\Organization;
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

    private function actingAsEmployer(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);
    }
}
