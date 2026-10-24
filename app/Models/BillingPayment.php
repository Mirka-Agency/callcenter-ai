<?php

namespace App\Models;

use App\Domain\Platform\Enums\PaymentMethod;
use App\Domain\Platform\Enums\PaymentType;
use App\Models\Concerns\AuditedByAdmins;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'billing_invoice_id', 'type', 'amount_rials', 'paid_at', 'method', 'reference', 'notes', 'recorded_by_user_id'])]
class BillingPayment extends Model
{
    use AuditedByAdmins;

    protected function casts(): array
    {
        return [
            'type' => PaymentType::class,
            'method' => PaymentMethod::class,
            'amount_rials' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** Positive for money received, negative for refunds. */
    public function signedAmount(): int
    {
        return $this->type === PaymentType::Refund ? -$this->amount_rials : $this->amount_rials;
    }
}
