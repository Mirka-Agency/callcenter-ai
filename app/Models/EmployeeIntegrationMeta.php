<?php

namespace App\Models;

use App\Services\Reports\DefinedExtensionCallConstraint;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'organization_user_id',
    'integratable_type',
    'integratable_id',
    'key',
    'value',
])]
class EmployeeIntegrationMeta extends Model
{
    protected $table = 'employee_integration_meta';

    protected static function booted(): void
    {
        $refresh = function (EmployeeIntegrationMeta $meta): void {
            if ($meta->key !== 'extension') {
                return;
            }

            $organizationId = OrganizationUser::query()
                ->whereKey($meta->organization_user_id)
                ->value('organization_id');

            if ($organizationId) {
                app(DefinedExtensionCallConstraint::class)
                    ->refreshOrganization((int) $organizationId);
            }
        };

        static::saved($refresh);
        static::deleted($refresh);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(OrganizationUser::class, 'organization_user_id');
    }

    public function integratable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getConnectionLabelAttribute(): string
    {
        $integratable = $this->integratable;

        if (! $integratable) {
            return '—';
        }

        $type = class_basename($this->integratable_type);
        $provider = $integratable->provider?->name ?? $type;

        return "{$provider} · {$integratable->name}";
    }
}
