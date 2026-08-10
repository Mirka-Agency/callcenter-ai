<?php

namespace Tests\Unit;

use App\Domain\Voip\Enums\VoipProviderCode;
use App\Infrastructure\Voip\Adapters\CustomVoipAdapter;
use App\Infrastructure\Voip\Adapters\NullVoipAdapter;
use App\Infrastructure\Voip\Adapters\NovatelVoipAdapter;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Models\IntegrationMetaDefinition;
use App\Models\VoipProvider;
use App\Services\IntegrationMetaDefinitionSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationMetaDefinitionSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_voip_provider_gets_extension_field_including_null_adapter(): void
    {
        $providers = [
            VoipProvider::query()->create([
                'name' => 'Navatel',
                'code' => VoipProviderCode::Novatel->value,
                'adapter_class' => NovatelVoipAdapter::class,
                'is_active' => true,
            ]),
            VoipProvider::query()->create([
                'name' => 'Simotel',
                'code' => VoipProviderCode::Simotel->value,
                'adapter_class' => SimotelVoipAdapter::class,
                'is_active' => true,
            ]),
            VoipProvider::query()->create([
                'name' => 'Asterisk',
                'code' => VoipProviderCode::Custom->value,
                'adapter_class' => CustomVoipAdapter::class,
                'is_active' => true,
            ]),
            VoipProvider::query()->create([
                'name' => 'Broken / fallback',
                'code' => 'legacy',
                'adapter_class' => NullVoipAdapter::class,
                'is_active' => true,
            ]),
        ];

        $synced = app(IntegrationMetaDefinitionSynchronizer::class)->syncAll();

        $this->assertGreaterThanOrEqual(4, $synced);

        foreach ($providers as $provider) {
            $this->assertDatabaseHas('integration_meta_definitions', [
                'provider_type' => VoipProvider::class,
                'provider_id' => $provider->id,
                'key' => 'extension',
                'is_required' => true,
            ]);
        }
    }

    public function test_sync_voip_provider_ensures_extension_even_without_meta_interface(): void
    {
        $provider = VoipProvider::query()->create([
            'name' => 'Fallback',
            'code' => 'fallback',
            'adapter_class' => NullVoipAdapter::class,
            'is_active' => true,
        ]);

        // Clear definitions created by VoipProvider::saved hook so we test sync explicitly.
        IntegrationMetaDefinition::query()
            ->where('provider_type', VoipProvider::class)
            ->where('provider_id', $provider->id)
            ->delete();

        $synced = app(IntegrationMetaDefinitionSynchronizer::class)->syncVoipProvider($provider);

        $this->assertSame(1, $synced);
        $this->assertDatabaseHas('integration_meta_definitions', [
            'provider_type' => VoipProvider::class,
            'provider_id' => $provider->id,
            'key' => 'extension',
            'name' => 'شماره داخلی',
        ]);
    }
}
