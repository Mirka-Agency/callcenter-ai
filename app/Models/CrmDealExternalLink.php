<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'crm_deal_id',
    'organization_crm_connection_id',
    'external_id',
    'external_pipeline_id',
    'external_stage_id',
    'sync_status',
    'last_error',
    'last_synced_at',
])]
class CrmDealExternalLink extends Model
{
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(OrganizationCrmConnection::class, 'organization_crm_connection_id');
    }
}
