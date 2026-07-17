<?php

namespace App\Models;

use App\Services\IntegrationMetaDefinitionSynchronizer;
use Database\Factories\CrmProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'code', 'is_active', 'config'])]
class CrmProvider extends Model
{
    /** @use HasFactory<CrmProviderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (CrmProvider $provider): void {
            app(IntegrationMetaDefinitionSynchronizer::class)->syncCrmProvider($provider);
        });
    }

    public function connections(): HasMany
    {
        return $this->hasMany(OrganizationCrmConnection::class);
    }

    public function metaDefinitions(): MorphMany
    {
        return $this->morphMany(IntegrationMetaDefinition::class, 'provider');
    }
}
