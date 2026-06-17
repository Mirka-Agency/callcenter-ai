<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'code', 'is_active', 'config', 'api_key', 'base_url'])]
class LlmProvider extends Model
{
    /** @use HasFactory<\Database\Factories\LlmProviderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'config' => 'array',
            'api_key' => 'encrypted',
        ];
    }

    public function models(): HasMany
    {
        return $this->hasMany(LlmModel::class, 'provider_id');
    }

    public function hasApiCredentials(): bool
    {
        return filled($this->api_key);
    }

    public function metaDefinitions(): MorphMany
    {
        return $this->morphMany(IntegrationMetaDefinition::class, 'provider');
    }

    public function promptVersions(): HasMany
    {
        return $this->hasMany(LlmPromptVersion::class);
    }
}
