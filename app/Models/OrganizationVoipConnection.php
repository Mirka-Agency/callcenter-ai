<?php

namespace App\Models;

use Database\Factories\OrganizationVoipConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'organization_id',
    'voip_provider_id',
    'name',
    'webhook_token',
    'credentials',
    'settings',
    'is_default',
    'is_active',
    'ingestion_mode',
    'polling_enabled',
    'polling_interval_seconds',
    'last_polled_at',
])]
class OrganizationVoipConnection extends Model
{
    /** @use HasFactory<OrganizationVoipConnectionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'polling_enabled' => 'boolean',
            'polling_interval_seconds' => 'integer',
            'last_polled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OrganizationVoipConnection $connection): void {
            if (blank($connection->webhook_token)) {
                $connection->webhook_token = static::generateWebhookToken();
            }
        });

        static::saved(function (OrganizationVoipConnection $connection): void {
            if ($connection->is_default) {
                static::query()
                    ->where('organization_id', $connection->organization_id)
                    ->whereKeyNot($connection->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(VoipProvider::class, 'voip_provider_id');
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(VoipCallLog::class);
    }

    public function webhookLogs(): HasMany
    {
        return $this->hasMany(VoipWebhookLog::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(VoipSyncLog::class);
    }

    protected function inboundWebhookUrl(): Attribute
    {
        return Attribute::get(fn (): string => route('webhooks.voip', ['token' => $this->webhook_token]));
    }

    public static function generateWebhookToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::query()->where('webhook_token', $token)->exists());

        return $token;
    }

    public function regenerateWebhookToken(): string
    {
        $this->webhook_token = static::generateWebhookToken();
        $this->save();

        return $this->webhook_token;
    }
}
