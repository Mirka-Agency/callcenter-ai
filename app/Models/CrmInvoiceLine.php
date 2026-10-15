<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'crm_invoice_id',
    'crm_product_id',
    'description',
    'quantity',
    'unit_label',
    'unit_price_rials',
    'amount_rials',
    'sort_order',
])]
class CrmInvoiceLine extends Model
{
    protected static function booted(): void
    {
        static::saving(function (CrmInvoiceLine $line): void {
            if (! $line->organization_id) {
                throw new \RuntimeException('Invoice lines must belong to an organization.');
            }
            if (! $line->crm_invoice_id) {
                throw new \RuntimeException('Invoice lines must belong to an invoice.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price_rials' => 'integer',
            'amount_rials' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CrmInvoice::class, 'crm_invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CrmProduct::class, 'crm_product_id');
    }
}
