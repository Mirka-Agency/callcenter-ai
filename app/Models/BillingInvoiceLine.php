<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['billing_invoice_id', 'description', 'quantity', 'unit_price_rials', 'amount_rials'])]
class BillingInvoiceLine extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_rials' => 'integer',
            'amount_rials' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }
}
