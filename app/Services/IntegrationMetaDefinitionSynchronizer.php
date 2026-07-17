<?php

namespace App\Services;

use App\Contracts\ProvidesEmployeeIntegrationMeta;
use App\Infrastructure\Crm\CrmAdapterRegistry;
use App\Infrastructure\Voip\VoipAdapterRegistry;
use App\Models\CrmProvider;
use App\Models\IntegrationMetaDefinition;
use App\Models\VoipProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class IntegrationMetaDefinitionSynchronizer
{
    public function syncAll(): int
    {
        if (! Schema::hasTable('integration_meta_definitions')) {
            return 0;
        }

        $synced = 0;
        $crmAdapters = app(CrmAdapterRegistry::class)->all();
        $voipAdapters = app(VoipAdapterRegistry::class)->all();

        CrmProvider::query()->each(function (CrmProvider $provider) use ($crmAdapters, &$synced): void {
            $synced += $this->syncProvider($provider, $crmAdapters[$provider->code] ?? null);
        });

        VoipProvider::query()->each(function (VoipProvider $provider) use ($voipAdapters, &$synced): void {
            $adapterClass = $provider->adapter_class ?: ($voipAdapters[$provider->code] ?? null);
            $synced += $this->syncProvider($provider, $adapterClass);
        });

        return $synced;
    }

    public function syncCrmProvider(CrmProvider $provider): int
    {
        if (! Schema::hasTable('integration_meta_definitions')) {
            return 0;
        }

        $adapterClass = app(CrmAdapterRegistry::class)->all()[$provider->code] ?? null;

        return $this->syncProvider($provider, $adapterClass);
    }

    public function syncVoipProvider(VoipProvider $provider): int
    {
        if (! Schema::hasTable('integration_meta_definitions')) {
            return 0;
        }

        $adapterClass = $provider->adapter_class
            ?: (app(VoipAdapterRegistry::class)->all()[$provider->code] ?? null);

        return $this->syncProvider($provider, $adapterClass);
    }

    /** @param class-string|null $adapterClass */
    private function syncProvider(Model $provider, ?string $adapterClass): int
    {
        if (
            ! is_string($adapterClass)
            || ! is_subclass_of($adapterClass, ProvidesEmployeeIntegrationMeta::class)
        ) {
            return 0;
        }

        $synced = 0;

        foreach ($adapterClass::employeeIntegrationMetaDefinitions() as $definition) {
            IntegrationMetaDefinition::query()->updateOrCreate(
                [
                    'provider_type' => $provider::class,
                    'provider_id' => $provider->getKey(),
                    'key' => $definition['key'],
                ],
                [
                    'name' => $definition['name'],
                    'field_type' => $definition['field_type'],
                    'is_required' => $definition['is_required'],
                    'placeholder' => $definition['placeholder'] ?? null,
                    'help_text' => $definition['help_text'] ?? null,
                    'sort_order' => $definition['sort_order'] ?? 0,
                ],
            );

            $synced++;
        }

        return $synced;
    }
}
