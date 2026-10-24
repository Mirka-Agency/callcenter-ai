<?php

namespace App\Models;

use App\Models\Concerns\AuditedByAdmins;
use Database\Factories\OrganizationCrmConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'crm_provider_id',
    'name',
    'credentials',
    'settings',
    'is_default',
    'is_active',
])]
class OrganizationCrmConnection extends Model
{
    use AuditedByAdmins;

    /** @use HasFactory<OrganizationCrmConnectionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (OrganizationCrmConnection $connection): void {
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
        return $this->belongsTo(CrmProvider::class, 'crm_provider_id');
    }

    public function connectionLogs(): HasMany
    {
        return $this->hasMany(CrmConnectionLog::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(CrmSyncLog::class);
    }
}
