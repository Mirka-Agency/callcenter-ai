<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\AiBilling\PlatformAiSettingsPage;
use App\Filament\Resources\LlmProviders\LlmProviderResource;
use App\Filament\Resources\OrganizationWallets\OrganizationWalletResource;
use App\Models\Organization;
use App\Models\User;
use App\Services\AiBillingService;
use App\Services\WalletService;
use App\Support\Navigation\EmployerNavigation;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnPremBillingHiddenTest extends TestCase
{
    use RefreshDatabase;

    public function test_employer_panel_hides_wallet_when_onprem_is_enabled(): void
    {
        config(['onprem.enabled' => true]);

        $employer = User::factory()->create(['role' => UserRole::Employer]);
        Organization::factory()->create(['user_id' => $employer->id]);

        $this->assertFalse(collect(EmployerNavigation::items())->contains(
            fn (array $item): bool => $item['route'] === 'employer.wallet.index',
        ));

        $this->actingAs($employer)
            ->get(route('employer.dashboard'))
            ->assertOk()
            ->assertDontSee('اعتبار هوش مصنوعی', false);

        $this->actingAs($employer)
            ->get(route('employer.wallet.index'))
            ->assertNotFound();
    }

    public function test_employer_wallet_stays_visible_in_cloud_mode(): void
    {
        config(['onprem.enabled' => false]);

        $employer = User::factory()->create(['role' => UserRole::Employer]);
        Organization::factory()->create(['user_id' => $employer->id]);

        $this->assertTrue(collect(EmployerNavigation::items())->contains(
            fn (array $item): bool => $item['route'] === 'employer.wallet.index',
        ));

        $this->actingAs($employer)
            ->get(route('employer.dashboard'))
            ->assertOk()
            ->assertSee('اعتبار هوش مصنوعی', false);
    }

    public function test_admin_billing_pages_are_hidden_when_onprem_is_enabled(): void
    {
        config(['onprem.enabled' => true]);

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        $this->assertFalse(PlatformAiSettingsPage::canAccess());
        $this->assertFalse(OrganizationWalletResource::canAccess());
        $this->assertTrue(LlmProviderResource::canAccess());

        $labels = $this->adminNavigationLabels();

        $this->assertNotContains(__('filament.navigation.platform_billing'), $labels);
        $this->assertNotContains(__('filament.navigation.organization_wallets'), $labels);
        $this->assertContains(__('filament.navigation.llm_providers'), $labels);

        $this->get(PlatformAiSettingsPage::getUrl())
            ->assertForbidden();
        $this->get(OrganizationWalletResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_analysis_is_not_blocked_by_wallet_when_onprem_is_enabled(): void
    {
        config(['onprem.enabled' => true]);

        $organization = Organization::factory()->create();
        $wallet = app(WalletService::class)->forOrganization($organization->id);
        $wallet->update(['balance' => 0]);

        $this->assertTrue(app(WalletService::class)->hasSufficientBalance($organization->id, 1));

        app(AiBillingService::class)->assertCanAnalyze($organization->id);
    }

    /** @return list<string|null> */
    private function adminNavigationLabels(): array
    {
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        return collect($panel->getNavigation())
            ->flatMap(function (NavigationGroup|NavigationItem $group): array {
                if ($group instanceof NavigationItem) {
                    return [$group->getLabel()];
                }

                return collect($group->getItems())
                    ->map(fn (NavigationItem $item): ?string => $item->getLabel())
                    ->all();
            })
            ->all();
    }
}
