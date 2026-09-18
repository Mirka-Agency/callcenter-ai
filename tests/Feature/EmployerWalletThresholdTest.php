<?php

namespace Tests\Feature;

use App\Domain\Llm\Enums\LlmProviderCode;
use App\Enums\UserRole;
use App\Livewire\Employer\Wallet\Index;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\Organization;
use App\Models\OrganizationWallet;
use App\Models\PlatformAiSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployerWalletThresholdTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_page_shows_default_threshold_and_allows_editing(): void
    {
        $this->actingAsEmployer();

        Livewire::test(Index::class)
            ->assertSee('آستانه هشدار')
            ->assertSee('تغییر')
            ->assertSet('editingThreshold', false)
            ->call('startEditingThreshold')
            ->assertSet('editingThreshold', true)
            ->assertSee('ذخیره')
            ->assertSee('انصراف');
    }

    public function test_employer_can_set_a_custom_warning_threshold(): void
    {
        $organization = $this->actingAsEmployer();

        Livewire::test(Index::class)
            ->call('startEditingThreshold')
            ->set('thresholdInput', '۲۵۰٬۰۰۰')
            ->call('saveThreshold')
            ->assertHasNoErrors()
            ->assertSet('editingThreshold', false);

        $wallet = OrganizationWallet::query()
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $this->assertEquals(250_000, (float) $wallet->low_balance_threshold);
        $this->assertSame(250_000.0, $wallet->lowBalanceThreshold());
    }

    public function test_invalid_threshold_is_rejected(): void
    {
        $this->actingAsEmployer();

        Livewire::test(Index::class)
            ->call('startEditingThreshold')
            ->set('thresholdInput', '0')
            ->call('saveThreshold')
            ->assertHasErrors(['thresholdInput'])
            ->assertSet('editingThreshold', true);
    }

    public function test_custom_threshold_controls_the_low_balance_banner(): void
    {
        $organization = $this->actingAsEmployer();
        $wallet = $organization->wallet()->firstOrFail();
        $wallet->update(['balance' => 80_000]);

        Livewire::test(Index::class)
            ->assertSee('موجودی کم است');

        Livewire::test(Index::class)
            ->call('startEditingThreshold')
            ->set('thresholdInput', '50000')
            ->call('saveThreshold')
            ->assertDontSee('موجودی کم است');
    }

    private function actingAsEmployer(): Organization
    {
        $this->seedPlatformLlm();

        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        $this->actingAs($employer);

        return $organization;
    }

    private function seedPlatformLlm(): void
    {
        $provider = LlmProvider::query()->create([
            'name' => 'OpenAI',
            'code' => LlmProviderCode::OpenAi->value,
            'api_key' => 'test-key',
            'is_active' => true,
        ]);

        $model = LlmModel::query()->create([
            'provider_id' => $provider->id,
            'name' => 'gpt-4o',
            'model_key' => 'gpt-4o',
            'input_price_per_million_tokens' => 1,
            'output_price_per_million_tokens' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        PlatformAiSettings::current()->update([
            'default_llm_provider_id' => $provider->id,
            'default_llm_model_id' => $model->id,
        ]);
    }
}
