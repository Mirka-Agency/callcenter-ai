<?php

namespace App\Models;

use App\Domain\Platform\Enums\InvoiceStatus;
use App\Domain\Platform\Enums\PaymentType;
use App\Models\Concerns\AuditedByAdmins;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id', 'subscription_id', 'number', 'status', 'issued_at', 'due_at', 'paid_at',
    'subtotal_rials', 'discount_rials', 'tax_rate', 'tax_rials', 'total_rials', 'buyer', 'notes',
])]
class BillingInvoice extends Model
{
    use AuditedByAdmins;

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal_rials' => 'integer',
            'discount_rials' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_rials' => 'integer',
            'total_rials' => 'integer',
            'buyer' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillingInvoiceLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class);
    }

    public function paidRials(): int
    {
        return (int) $this->payments->sum(fn (BillingPayment $payment): int => $payment->signedAmount());
    }

    public function balanceRials(): int
    {
        return max(0, $this->total_rials - $this->paidRials());
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Issued && $this->due_at?->isPast() === true;
    }

    /** Issued invoices not yet settled. */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Issued->value);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->outstanding()->whereNotNull('due_at')->where('due_at', '<', now());
    }

    public function refreshPaymentStatus(): void
    {
        $this->load('payments');

        if ($this->status === InvoiceStatus::Issued && $this->paidRials() >= $this->total_rials) {
            $this->forceFill([
                'status' => InvoiceStatus::Paid,
                'paid_at' => $this->payments->where('type', PaymentType::Payment)->max('paid_at') ?? now(),
            ])->save();
        } elseif ($this->status === InvoiceStatus::Paid && $this->paidRials() < $this->total_rials) {
            $this->forceFill(['status' => InvoiceStatus::Issued, 'paid_at' => null])->save();
        }
    }
}
