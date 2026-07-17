<?php

namespace App\Models;

use App\Services\IntegrationMetaDefinitionSynchronizer;
use Database\Factories\VoipProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'code', 'adapter_class', 'supports_webhook', 'supports_polling', 'polling_interval_seconds', 'is_active', 'config'])]
class VoipProvider extends Model
{
    /** @use HasFactory<VoipProviderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'supports_webhook' => 'boolean',
            'supports_polling' => 'boolean',
            'polling_interval_seconds' => 'integer',
            'config' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (VoipProvider $provider): void {
            app(IntegrationMetaDefinitionSynchronizer::class)->syncVoipProvider($provider);
        });
    }

    public function connections(): HasMany
    {
        return $this->hasMany(OrganizationVoipConnection::class);
    }

    public function metaDefinitions(): MorphMany
    {
        return $this->morphMany(IntegrationMetaDefinition::class, 'provider');
    }
}
