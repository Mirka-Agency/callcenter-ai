<?php

namespace App\Application\Crm\Invoices\Actions;

use App\Application\Crm\Invoices\InvoiceAuthorization;
use App\Application\Crm\Invoices\InvoiceMoney;
use App\Application\Crm\Invoices\InvoiceTotals;
use App\Domain\Crm\Invoices\InvoiceStatus;
use App\Models\CrmInvoice;
use App\Models\CrmInvoiceLine;
use App\Models\CrmProduct;
use App\Models\Customer;
use App\Models\User;
use App\Support\PersianNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInvoice
{
    public function __construct(private InvoiceAuthorization $auth) {}

    /** @param  array<string, mixed>  $input */
    public function handle(CrmInvoice $invoice, User $actor, array $input): CrmInvoice
    {
        $organizationId = (int) $invoice->organization_id;
        $this->auth->assertInternalCrm($organizationId);
        $this->auth->authorize($this->auth->canUpdate($actor, $invoice));

        if ($invoice->status === InvoiceStatus::Issued) {
            throw ValidationException::withMessages([
                'status' => 'سند صادرشده قابل ویرایش نیست. برای تغییر، سند جدیدی بسازید.',
            ]);
        }

        $payload = $this->validated($organizationId, $input);

        return DB::transaction(function () use ($invoice, $organizationId, $payload): CrmInvoice {
            $invoice->fill($payload['header']);
            $invoice->save();

            CrmInvoiceLine::query()
                ->where('crm_invoice_id', $invoice->id)
                ->delete();

            foreach ($payload['lines'] as $index => $line) {
                CrmInvoiceLine::query()->create([
                    ...$line,
                    'organization_id' => $organizationId,
                    'crm_invoice_id' => $invoice->id,
                    'sort_order' => $index,
                ]);
            }

            $totals = InvoiceTotals::compute($payload['lines'], (float) $payload['header']['tax_percent']);
            $invoice->fill($totals);
            $invoice->save();

            return $invoice->fresh(['lines', 'customer', 'organization']) ?? $invoice;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>}
     */
    private function validated(int $organizationId, array $input): array
    {
        $customerId = $input['customer_id'] ?? null;
        $customerId = $customerId === null || $customerId === '' ? null : (int) $customerId;

        if ($customerId !== null) {
            $exists = Customer::query()
                ->where('organization_id', $organizationId)
                ->whereKey($customerId)
                ->exists();
            if (! $exists) {
                throw ValidationException::withMessages([
                    'customer_id' => 'مخاطب انتخاب‌شده معتبر نیست.',
                ]);
            }
        }

        $taxPercent = $this->taxPercent($input['tax_percent'] ?? 10);

        $linesInput = $input['lines'] ?? [];
        if (! is_array($linesInput)) {
            throw ValidationException::withMessages([
                'lines' => 'ردیف‌های سند نامعتبر است.',
            ]);
        }

        $lines = [];
        foreach (array_values($linesInput) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $description = trim((string) ($row['description'] ?? ''));
            if ($description === '') {
                throw ValidationException::withMessages([
                    "lines.{$index}.description" => 'شرح ردیف الزامی است.',
                ]);
            }
            if (mb_strlen($description) > 500) {
                throw ValidationException::withMessages([
                    "lines.{$index}.description" => 'شرح ردیف حداکثر ۵۰۰ نویسه دارد.',
                ]);
            }

            $quantity = $this->quantity($row['quantity'] ?? 1, $index);
            $unitLabel = $this->unitLabel($row['unit_label'] ?? null, $index);
            $unitPrice = InvoiceMoney::parseRials($row['unit_price_rials'] ?? 0, "lines.{$index}.unit_price_rials") ?? 0;

            $productId = $row['crm_product_id'] ?? null;
            $productId = $productId === null || $productId === '' ? null : (int) $productId;
            if ($productId !== null) {
                $productExists = CrmProduct::query()
                    ->where('organization_id', $organizationId)
                    ->whereKey($productId)
                    ->exists();
                if (! $productExists) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.crm_product_id" => 'محصول کاتالوگ معتبر نیست.',
                    ]);
                }
            }

            $amount = InvoiceTotals::lineAmount($quantity, $unitPrice);

            $lines[] = [
                'crm_product_id' => $productId,
                'description' => $description,
                'quantity' => $quantity,
                'unit_label' => $unitLabel,
                'unit_price_rials' => $unitPrice,
                'amount_rials' => $amount,
            ];
        }

        return [
            'header' => [
                'customer_id' => $customerId,
                'buyer_name' => $this->nullableString($input['buyer_name'] ?? null, 180, 'buyer_name'),
                'buyer_company' => $this->nullableString($input['buyer_company'] ?? null, 180, 'buyer_company'),
                'buyer_phone' => $this->nullableString($input['buyer_phone'] ?? null, 64, 'buyer_phone'),
                'buyer_national_id' => $this->nullableString($input['buyer_national_id'] ?? null, 64, 'buyer_national_id'),
                'buyer_economic_code' => $this->nullableString($input['buyer_economic_code'] ?? null, 64, 'buyer_economic_code'),
                'buyer_address' => $this->nullableString($input['buyer_address'] ?? null, 2000, 'buyer_address'),
                'notes' => $this->nullableString($input['notes'] ?? null, 5000, 'notes'),
                'tax_percent' => $taxPercent,
            ],
            'lines' => $lines,
        ];
    }

    private function taxPercent(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 10.0;
        }

        $parsed = PersianNumber::parse(trim((string) $value));
        if (! is_int($parsed) && ! is_float($parsed)) {
            throw ValidationException::withMessages([
                'tax_percent' => 'درصد مالیات نامعتبر است.',
            ]);
        }

        $percent = (float) $parsed;
        if ($percent < 0 || $percent > 100) {
            throw ValidationException::withMessages([
                'tax_percent' => 'درصد مالیات باید بین ۰ و ۱۰۰ باشد.',
            ]);
        }

        return round($percent, 2);
    }

    private function quantity(mixed $value, int $index): float
    {
        if ($value === null || $value === '') {
            return 1.0;
        }

        $parsed = PersianNumber::parse(trim((string) $value));
        if (! is_int($parsed) && ! is_float($parsed)) {
            throw ValidationException::withMessages([
                "lines.{$index}.quantity" => 'تعداد نامعتبر است.',
            ]);
        }

        $qty = (float) $parsed;
        if ($qty <= 0) {
            throw ValidationException::withMessages([
                "lines.{$index}.quantity" => 'تعداد باید بزرگ‌تر از صفر باشد.',
            ]);
        }

        return $qty;
    }

    private function unitLabel(mixed $value, int $index): ?string
    {
        $label = trim((string) ($value ?? ''));
        if ($label === '') {
            return null;
        }

        if (mb_strlen($label) > 64) {
            throw ValidationException::withMessages([
                "lines.{$index}.unit_label" => 'واحد حداکثر ۶۴ نویسه دارد.',
            ]);
        }

        return $label;
    }

    private function nullableString(mixed $value, int $max, string $field): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > $max) {
            throw ValidationException::withMessages([
                $field => "حداکثر {$max} نویسه مجاز است.",
            ]);
        }

        return $text;
    }
}
