<?php

namespace App\Application\Crm\Invoices\Actions;

use App\Application\Crm\Invoices\InvoiceAuthorization;
use App\Domain\Crm\Invoices\InvoiceDocumentType;
use App\Domain\Crm\Invoices\InvoiceStatus;
use App\Models\CrmInvoice;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInvoice
{
    public function __construct(private InvoiceAuthorization $auth) {}

    /** @param  array<string, mixed>  $input */
    public function handle(int $organizationId, User $actor, array $input): CrmInvoice
    {
        $this->auth->assertInternalCrm($organizationId);
        $this->auth->authorize($this->auth->canManage($actor, $organizationId));

        $payload = $this->validated($organizationId, $input);

        return DB::transaction(function () use ($organizationId, $actor, $payload): CrmInvoice {
            return CrmInvoice::query()->create([
                ...$payload,
                'organization_id' => $organizationId,
                'status' => InvoiceStatus::Draft,
                'tax_percent' => 10,
                'subtotal_rials' => 0,
                'tax_rials' => 0,
                'total_rials' => 0,
                'created_by_user_id' => $actor->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     document_type: InvoiceDocumentType,
     *     customer_id: ?int,
     *     buyer_name: ?string,
     *     buyer_company: ?string,
     *     buyer_phone: ?string,
     *     buyer_address: ?string
     * }
     */
    private function validated(int $organizationId, array $input): array
    {
        $type = InvoiceDocumentType::tryFrom((string) ($input['document_type'] ?? ''));
        if (! $type instanceof InvoiceDocumentType) {
            throw ValidationException::withMessages([
                'document_type' => 'نوع سند باید پیش‌فاکتور یا فاکتور باشد.',
            ]);
        }

        $customerId = $input['customer_id'] ?? null;
        $customerId = $customerId === null || $customerId === '' ? null : (int) $customerId;

        $buyer = [
            'buyer_name' => null,
            'buyer_company' => null,
            'buyer_phone' => null,
            'buyer_address' => null,
        ];

        if ($customerId !== null) {
            $customer = Customer::query()
                ->where('organization_id', $organizationId)
                ->with('company')
                ->find($customerId);

            if (! $customer instanceof Customer) {
                throw ValidationException::withMessages([
                    'customer_id' => 'مخاطب انتخاب‌شده معتبر نیست.',
                ]);
            }

            $buyer['buyer_name'] = $customer->name ?: null;
            $buyer['buyer_phone'] = $customer->phone_number ?: null;
            $buyer['buyer_company'] = $customer->company?->name
                ?: (filled($customer->company_name) ? (string) $customer->company_name : null);
            $buyer['buyer_address'] = $customer->company?->address ?: null;
        }

        return [
            'document_type' => $type,
            'customer_id' => $customerId,
            ...$buyer,
        ];
    }
}
