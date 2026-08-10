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
    /**
     * Default extension field every VoIP provider must expose so employers
     * can map calls to employees regardless of adapter-specific meta.
     *
     * @return array{
     *     key: string,
     *     name: string,
     *     field_type: string,
     *     is_required: bool,
     *     placeholder: string,
     *     help_text: string,
     *     sort_order: int
     * }
     */
    public static function defaultVoipExtensionDefinition(): array
    {
        return [
            'key' => 'extension',
            'name' => 'شماره داخلی',
            'field_type' => 'text',
            'is_required' => true,
            'placeholder' => '101',
            'help_text' => 'شماره داخلی کارشناس روی این اتصال VoIP. باید با مقدار extension تماس‌های ورودی یکسان باشد.',
            'sort_order' => 1,
        ];
    }

    public function syncAll(): int
    {
        if (! Schema::hasTable('integration_meta_definitions')) {
            return 0;
        }

        $synced = 0;
        $crmAdapters = app(CrmAdapterRegistry::class)->all();
        $voipAdapters = app(VoipAdapterRegistry::class)->all();

        CrmProvider::query()->each(function (CrmProvider $provider) use ($crmAdapters, &$synced): void {
            $synced += $this->syncAdapterDefinitions($provider, $crmAdapters[$provider->code] ?? null);
        });

        VoipProvider::query()->each(function (VoipProvider $provider) use ($voipAdapters, &$synced): void {
            $adapterClass = $provider->adapter_class ?: ($voipAdapters[$provider->code] ?? null);
            $synced += $this->syncVoipProviderDefinitions($provider, $adapterClass);
        });

        return $synced;
    }

    public function syncCrmProvider(CrmProvider $provider): int
    {
        if (! Schema::hasTable('integration_meta_definitions')) {
            return 0;
        }

        $adapterClass = app(CrmAdapterRegistry::class)->all()[$provider->code] ?? null;

        return $this->syncAdapterDefinitions($provider, $adapterClass);
    }

    public function syncVoipProvider(VoipProvider $provider): int
    {
        if (! Schema::hasTable('integration_meta_definitions')) {
            return 0;
        }

        $adapterClass = $provider->adapter_class
            ?: (app(VoipAdapterRegistry::class)->all()[$provider->code] ?? null);

        return $this->syncVoipProviderDefinitions($provider, $adapterClass);
    }

    /** @param class-string|null $adapterClass */
    private function syncVoipProviderDefinitions(VoipProvider $provider, ?string $adapterClass): int
    {
        // Always ensure employers can set an extension on every VoIP provider.
        $synced = $this->upsertDefinition($provider, self::defaultVoipExtensionDefinition());

        // Adapter-specific fields (may refine the extension help text / add more keys).
        $synced += $this->syncAdapterDefinitions($provider, $adapterClass);

        return $synced;
    }

    /** @param class-string|null $adapterClass */
    private function syncAdapterDefinitions(Model $provider, ?string $adapterClass): int
    {
        if (
            ! is_string($adapterClass)
            || ! is_subclass_of($adapterClass, ProvidesEmployeeIntegrationMeta::class)
        ) {
            return 0;
        }

        $synced = 0;

        foreach ($adapterClass::employeeIntegrationMetaDefinitions() as $definition) {
            $synced += $this->upsertDefinition($provider, $definition);
        }

        return $synced;
    }

    /** @param array<string, mixed> $definition */
    private function upsertDefinition(Model $provider, array $definition): int
    {
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

        return 1;
    }
}
