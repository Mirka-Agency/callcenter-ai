<?php

namespace Tests\Feature;

use App\Domain\Voip\Contracts\VoipAdapterInterface;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Infrastructure\Voip\Adapters\CustomVoipAdapter;
use App\Infrastructure\Voip\Adapters\NovatelVoipAdapter;
use App\Infrastructure\Voip\Adapters\NullVoipAdapter;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Infrastructure\Voip\VoipAdapterFactory;
use App\Infrastructure\Voip\VoipAdapterRegistry;
use App\Models\VoipProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoipAdapterFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_instantiates_configured_adapter_class(): void
    {
        $adapter = app(VoipAdapterFactory::class)->make(NovatelVoipAdapter::class, 'test');

        $this->assertInstanceOf(NovatelVoipAdapter::class, $adapter);
        $this->assertInstanceOf(VoipAdapterInterface::class, $adapter);
    }

    public function test_factory_falls_back_to_default_when_primary_class_missing(): void
    {
        $adapter = app(VoipAdapterFactory::class)->make('App\\VoIP\\Adapters\\MissingAdapter', 'test');

        $this->assertInstanceOf(NovatelVoipAdapter::class, $adapter);
    }

    public function test_factory_falls_back_to_null_when_all_candidates_invalid(): void
    {
        config([
            'voip.adapter_class' => null,
            'voip.default_adapter' => 'App\\VoIP\\Adapters\\BrokenDefault',
            'voip.fallback_adapter' => NullVoipAdapter::class,
        ]);

        $adapter = app(VoipAdapterFactory::class)->make('App\\VoIP\\Adapters\\MissingAdapter', 'test');

        $this->assertInstanceOf(NullVoipAdapter::class, $adapter);
    }

    public function test_registry_resolves_adapter_from_database_provider_mapping(): void
    {
        VoipProvider::query()->create([
            'name' => 'Test Provider',
            'code' => VoipProviderCode::Novatel->value,
            'adapter_class' => NovatelVoipAdapter::class,
            'is_active' => true,
        ]);

        app(VoipAdapterRegistry::class)->loadFromDatabase();

        $adapter = app(VoipAdapterRegistry::class)->resolve(VoipProviderCode::Novatel);

        $this->assertInstanceOf(NovatelVoipAdapter::class, $adapter);
    }

    public function test_registry_resolves_simotel_adapter_from_config(): void
    {
        $adapter = app(VoipAdapterRegistry::class)->resolve(VoipProviderCode::Simotel);

        $this->assertInstanceOf(SimotelVoipAdapter::class, $adapter);
    }

    public function test_registry_resolves_custom_adapter_from_config(): void
    {
        $adapter = app(VoipAdapterRegistry::class)->resolve(VoipProviderCode::Custom);

        $this->assertInstanceOf(CustomVoipAdapter::class, $adapter);
    }
}
