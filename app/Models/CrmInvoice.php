<?php

namespace App\Models;

use App\Application\Crm\Invoices\InvoiceMoney;
use App\Domain\Crm\Invoices\InvoiceDocumentType;
use App\Domain\Crm\Invoices\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id',
    'document_type',
    'status',
    'number',
    'issued_at',
    'customer_id',
    'buyer_name',
    'buyer_company',
    'buyer_phone',
    'buyer_national_id',
    'buyer_economic_code',
    'buyer_address',
    'notes',
    'tax_percent',
    'subtotal_rials',
    'tax_rials',
    'total_rials',
    'notify_sms_on_issue',
    'created_by_user_id',
])]
class CrmInvoice extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (CrmInvoice $invoice): void {
            if (! $invoice->organization_id) {
                throw new \RuntimeException('Invoices must belong to an organization.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'document_type' => InvoiceDocumentType::class,
            'status' => InvoiceStatus::class,
            'issued_at' => 'datetime',
            'tax_percent' => 'float',
            'subtotal_rials' => 'integer',
            'tax_rials' => 'integer',
            'total_rials' => 'integer',
            'notify_sms_on_issue' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CrmInvoiceLine::class, 'crm_invoice_id')->orderBy('sort_order')->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function isIssued(): bool
    {
        return $this->status === InvoiceStatus::Issued;
    }

    public function buyerDisplayName(): string
    {
        $name = trim((string) ($this->buyer_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $company = trim((string) ($this->buyer_company ?? ''));
        if ($company !== '') {
            return $company;
        }

        return '—';
    }

    public function formattedTotal(): string
    {
        return InvoiceMoney::formatRials((int) $this->total_rials);
    }

    public function displayNumber(): string
    {
        return filled($this->number) ? (string) $this->number : '—';
    }
}
