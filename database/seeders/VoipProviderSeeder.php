<?php

namespace Database\Seeders;

use App\Domain\Voip\Enums\VoipProviderCode;
use App\Infrastructure\Voip\Adapters\CustomVoipAdapter;
use App\Infrastructure\Voip\Adapters\NovatelVoipAdapter;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Models\IntegrationMetaDefinition;
use App\Models\VoipProvider;
use Illuminate\Database\Seeder;

class VoipProviderSeeder extends Seeder
{
    public function run(): void
    {
        $knownCodes = [
            VoipProviderCode::Novatel->value,
            VoipProviderCode::Simotel->value,
            VoipProviderCode::Custom->value,
        ];

        VoipProvider::query()
            ->whereNotIn('code', $knownCodes)
            ->each(function (VoipProvider $provider): void {
                IntegrationMetaDefinition::query()
                    ->where('provider_type', VoipProvider::class)
                    ->where('provider_id', $provider->id)
                    ->delete();
                $provider->delete();
            });

        VoipProvider::query()->updateOrCreate(
            ['code' => VoipProviderCode::Novatel->value],
            [
                'name' => 'Navatel',
                'adapter_class' => NovatelVoipAdapter::class,
                'supports_webhook' => true,
                'supports_polling' => false,
                'polling_interval_seconds' => 30,
                'config' => ['default_api_url' => 'https://api.navatel.ir/v1'],
                'is_active' => true,
            ],
        );

        VoipProvider::query()->updateOrCreate(
            ['code' => VoipProviderCode::Simotel->value],
            [
                'name' => 'Simotel',
                'adapter_class' => SimotelVoipAdapter::class,
                'supports_webhook' => true,
                'supports_polling' => false,
                'polling_interval_seconds' => 30,
                'config' => ['default_api_url' => 'http://your-simotel-host/API/v4'],
                'is_active' => true,
            ],
        );

        VoipProvider::query()->updateOrCreate(
            ['code' => VoipProviderCode::Custom->value],
            [
                'name' => 'Custom',
                'adapter_class' => CustomVoipAdapter::class,
                'supports_webhook' => true,
                'supports_polling' => false,
                'polling_interval_seconds' => 30,
                'config' => ['default_api_url' => null],
                'is_active' => true,
            ],
        );
    }
}
