<?php

namespace Database\Seeders;

use App\Domain\Crm\Enums\CrmProviderCode;
use App\Models\CrmProvider;
use App\Models\IntegrationMetaDefinition;
use Illuminate\Database\Seeder;

class CrmProviderSeeder extends Seeder
{
    public function run(): void
    {
        $keptCodes = [
            CrmProviderCode::Didar->value,
            CrmProviderCode::Dynamics->value,
        ];

        CrmProvider::query()
            ->whereNotIn('code', $keptCodes)
            ->each(function (CrmProvider $provider): void {
                IntegrationMetaDefinition::query()
                    ->where('provider_type', CrmProvider::class)
                    ->where('provider_id', $provider->id)
                    ->delete();
                $provider->delete();
            });

        CrmProvider::query()->updateOrCreate(
            ['code' => CrmProviderCode::Didar->value],
            [
                'name' => CrmProviderCode::Didar->label(),
                'config' => [
                    'default_api_url' => CrmProviderCode::Didar->defaultApiUrl(),
                    'supports_webhooks' => true,
                ],
                'is_active' => true,
            ],
        );

        CrmProvider::query()->updateOrCreate(
            ['code' => CrmProviderCode::Dynamics->value],
            [
                'name' => CrmProviderCode::Dynamics->label(),
                'config' => [
                    'default_api_url' => 'https://yourorg.crm.dynamics.com',
                    'supports_webhooks' => false,
                    'auth' => 'oauth_client_credentials',
                ],
                'is_active' => true,
            ],
        );
    }
}
