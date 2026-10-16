<?php

namespace App\Models;

use App\Domain\Crm\Deals\DealCloseReasonKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'kind',
    'code',
    'label',
    'position',
    'is_active',
    'requires_note',
])]
class CrmDealCloseReason extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => DealCloseReasonKind::class,
            'position' => 'integer',
            'is_active' => 'boolean',
            'requires_note' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
