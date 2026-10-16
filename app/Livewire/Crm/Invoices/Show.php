<?php

namespace App\Livewire\Crm\Invoices;

use App\Application\Crm\Invoices\Actions\DeleteInvoice;
use App\Application\Crm\Invoices\Actions\IssueInvoice;
use App\Application\Crm\Invoices\Actions\UpdateInvoice;
use App\Application\Crm\Invoices\InvoiceAuthorization;
use App\Application\Crm\Invoices\InvoiceMoney;
use App\Application\Crm\Invoices\InvoiceQuery;
use App\Application\Crm\Invoices\InvoiceTotals;
use App\Domain\Crm\Invoices\InvoiceStatus;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\CrmInvoice;
use App\Models\CrmProduct;
use App\Models\Customer;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('سند فروش')]
class Show extends Component
{
    use ResolvesCrmContext;

    public int $invoiceId;

    public string $activeTab = 'document';

    public bool $editing = false;

    public string $customerId = '';

    public string $buyerName = '';

    public string $buyerCompany = '';

    public string $buyerPhone = '';

    public string $buyerNationalId = '';

    public string $buyerEconomicCode = '';

    public string $buyerAddress = '';

    public string $notes = '';

    public string $taxPercent = '10';

    public string $catalogProductId = '';

    public bool $notifySms = false;

    /** @var list<array{key: string, crm_product_id: ?int, description: string, quantity: string, unit_label: string, unit_price_rials: string}> */
    public array $lines = [];

    public function mount(int $invoice, InvoiceQuery $query): void
    {
        $this->invoiceId = $invoice;
        $invoiceModel = $query->findVisible($this->crmOrganizationId(), $this->crmUser(), $invoice);
        $this->syncForm($invoiceModel);
        $this->editing = $invoiceModel->isDraft();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['document', 'preview', 'history'], true) ? $tab : 'document';
    }

    public function startEditing(): void
    {
        if (! $this->canEditCurrent()) {
            return;
        }

        $this->editing = true;
        $this->activeTab = 'document';
    }

    public function stopEditing(): void
    {
        $this->editing = false;
    }

    public function updatedCustomerId(): void
    {
        if ($this->customerId === '') {
            return;
        }

        $customer = Customer::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->with('company')
            ->find((int) $this->customerId);

        if (! $customer instanceof Customer) {
            return;
        }

        $this->buyerName = (string) ($customer->name ?? '');
        $this->buyerPhone = (string) ($customer->phone_number ?? '');
        $this->buyerCompany = (string) ($customer->company?->name
            ?: ($customer->company_name ?? ''));
        $this->buyerAddress = (string) ($customer->company?->address ?? '');
    }

    public function addManualLine(): void
    {
        $this->lines[] = $this->emptyLine('قلم جدید');
    }

    public function addFromCatalog(): void
    {
        if ($this->catalogProductId === '') {
            return;
        }

        $product = CrmProduct::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->where('is_active', true)
            ->find((int) $this->catalogProductId);

        if (! $product instanceof CrmProduct) {
            return;
        }

        $row = [
            'key' => uniqid('line_', true),
            'crm_product_id' => (int) $product->id,
            'description' => (string) $product->name,
            'quantity' => '1',
            'unit_label' => (string) ($product->unit_label ?: 'عدد'),
            'unit_price_rials' => (string) InvoiceMoney::tomansToRials($product->price_tomans),
        ];

        if ($this->shouldReplacePlaceholderLine()) {
            $this->lines = [$row];
        } else {
            $this->lines[] = $row;
        }

        $this->catalogProductId = '';
    }

    public function removeLine(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(UpdateInvoice $action, InvoiceQuery $query): void
    {
        $invoice = $query->findVisible($this->crmOrganizationId(), $this->crmUser(), $this->invoiceId);
        $updated = $action->handle($invoice, $this->crmUser(), $this->payload());
        $this->syncForm($updated);
        $this->editing = $updated->isDraft();
        $this->toast('تغییرات ذخیره شد.');
    }

    public function issue(IssueInvoice $issue, UpdateInvoice $update, InvoiceQuery $query): void
    {
        $invoice = $query->findVisible($this->crmOrganizationId(), $this->crmUser(), $this->invoiceId);

        if ($invoice->isDraft()) {
            $invoice = $update->handle($invoice, $this->crmUser(), $this->payload());
        }

        $issued = $issue->handle($invoice, $this->crmUser(), $this->notifySms);
        $this->syncForm($issued);
        $this->editing = false;
        $this->toast('سند صادر شد.');
    }

    public function remove(DeleteInvoice $action, InvoiceQuery $query): void
    {
        $invoice = $query->findVisible($this->crmOrganizationId(), $this->crmUser(), $this->invoiceId);
        $action->handle($invoice, $this->crmUser());
        $this->toast('سند حذف شد.');
        $this->redirect(route($this->crmPortal().'.crm.invoices.index'), navigate: true);
    }

    #[Computed]
    public function computedTotals(): array
    {
        $lines = [];
        foreach ($this->lines as $line) {
            $qty = $this->parseFloat($line['quantity'] ?? '1') ?? 1.0;
            $price = $this->parseInt($line['unit_price_rials'] ?? '0') ?? 0;
            $lines[] = ['amount_rials' => InvoiceTotals::lineAmount($qty, $price)];
        }

        $tax = $this->parseFloat($this->taxPercent) ?? 10.0;

        return InvoiceTotals::compute($lines, $tax);
    }

    public function render(InvoiceQuery $query, InvoiceAuthorization $auth)
    {
        $organizationId = $this->crmOrganizationId();
        $actor = $this->crmUser();
        $invoice = $query->findVisible($organizationId, $actor, $this->invoiceId);

        $customers = Customer::query()
            ->where('organization_id', $organizationId)
            ->with('company')
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'name', 'phone_number', 'company_name', 'email', 'job_title', 'customer_company_id']);

        $products = CrmProduct::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'name', 'code', 'price_tomans', 'unit_label']);

        $canManage = $auth->canManage($actor, $organizationId);
        $editable = $canManage && $invoice->isDraft() && $this->editing;

        return view('livewire.crm.invoices.show', [
            'invoice' => $invoice,
            'customers' => $customers,
            'products' => $products,
            'totals' => $this->computedTotals,
            'canManage' => $canManage,
            'editable' => $editable,
            'portal' => $this->crmPortal(),
            'sellerName' => (string) ($invoice->organization?->title ?? ''),
            'creatorName' => (string) ($invoice->createdBy?->name ?: '—'),
            'linkedCustomer' => $invoice->customer,
            'history' => $this->historyEntries($invoice),
            'lifecycleSteps' => $this->lifecycleSteps($invoice),
        ])->layout($this->crmLayout())->title($invoice->displayTitle());
    }

    private function canEditCurrent(): bool
    {
        $invoice = CrmInvoice::query()
            ->where('organization_id', $this->crmOrganizationId())
            ->find($this->invoiceId);

        return $invoice instanceof CrmInvoice && $invoice->isDraft();
    }

    /** @return list<array{key: string, label: string, state: string}> */
    private function lifecycleSteps(CrmInvoice $invoice): array
    {
        $hasLines = $invoice->lines->isNotEmpty()
            && $invoice->lines->contains(fn ($line) => trim((string) $line->description) !== '' && (int) $line->amount_rials > 0);

        if ($invoice->isIssued()) {
            return [
                ['key' => 'draft', 'label' => 'پیش‌نویس', 'state' => 'done'],
                ['key' => 'ready', 'label' => 'آماده صدور', 'state' => 'done'],
                ['key' => 'issued', 'label' => 'صادر شده', 'state' => 'current'],
            ];
        }

        return [
            ['key' => 'draft', 'label' => 'پیش‌نویس', 'state' => $hasLines ? 'done' : 'current'],
            ['key' => 'ready', 'label' => 'آماده صدور', 'state' => $hasLines ? 'current' : 'upcoming'],
            ['key' => 'issued', 'label' => 'صادر شده', 'state' => 'upcoming'],
        ];
    }

    /** @return list<array{type: string, label: string, body: string, at: \Carbon\CarbonInterface|null}> */
    private function historyEntries(CrmInvoice $invoice): array
    {
        $entries = [
            [
                'type' => 'created',
                'label' => 'ایجاد سند',
                'body' => $invoice->document_type->label().' به‌صورت پیش‌نویس ثبت شد.',
                'at' => $invoice->created_at,
            ],
        ];

        if ($invoice->updated_at && $invoice->created_at && $invoice->updated_at->gt($invoice->created_at)) {
            $entries[] = [
                'type' => 'updated',
                'label' => 'آخرین ویرایش',
                'body' => 'اطلاعات یا ردیف‌های سند به‌روزرسانی شد.',
                'at' => $invoice->updated_at,
            ];
        }

        if ($invoice->isIssued() && $invoice->issued_at) {
            $entries[] = [
                'type' => 'issued',
                'label' => 'صدور سند',
                'body' => 'سند با شماره '.$invoice->displayNumber().' صادر شد.',
                'at' => $invoice->issued_at,
            ];
        }

        usort($entries, static function (array $a, array $b): int {
            return ($b['at']?->getTimestamp() ?? 0) <=> ($a['at']?->getTimestamp() ?? 0);
        });

        return $entries;
    }

    private function syncForm(CrmInvoice $invoice): void
    {
        $this->customerId = $invoice->customer_id ? (string) $invoice->customer_id : '';
        $this->buyerName = (string) ($invoice->buyer_name ?? '');
        $this->buyerCompany = (string) ($invoice->buyer_company ?? '');
        $this->buyerPhone = (string) ($invoice->buyer_phone ?? '');
        $this->buyerNationalId = (string) ($invoice->buyer_national_id ?? '');
        $this->buyerEconomicCode = (string) ($invoice->buyer_economic_code ?? '');
        $this->buyerAddress = (string) ($invoice->buyer_address ?? '');
        $this->notes = (string) ($invoice->notes ?? '');
        $this->taxPercent = rtrim(rtrim(number_format((float) $invoice->tax_percent, 2, '.', ''), '0'), '.') ?: '0';
        $this->notifySms = (bool) $invoice->notify_sms_on_issue;

        $this->lines = $invoice->lines->map(function ($line): array {
            $qty = (float) $line->quantity;

            return [
                'key' => 'db_'.$line->id,
                'crm_product_id' => $line->crm_product_id ? (int) $line->crm_product_id : null,
                'description' => (string) $line->description,
                'quantity' => fmod($qty, 1.0) === 0.0 ? (string) (int) $qty : rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.'),
                'unit_label' => (string) ($line->unit_label ?? ''),
                'unit_price_rials' => (string) (int) $line->unit_price_rials,
            ];
        })->values()->all();

        if ($this->lines === [] && $invoice->status === InvoiceStatus::Draft) {
            $this->lines[] = $this->emptyLine('قلم جدید');
        }
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'customer_id' => $this->customerId !== '' ? (int) $this->customerId : null,
            'buyer_name' => $this->buyerName,
            'buyer_company' => $this->buyerCompany,
            'buyer_phone' => $this->buyerPhone,
            'buyer_national_id' => $this->buyerNationalId,
            'buyer_economic_code' => $this->buyerEconomicCode,
            'buyer_address' => $this->buyerAddress,
            'notes' => $this->notes,
            'tax_percent' => $this->taxPercent,
            'lines' => array_map(static fn (array $line): array => [
                'crm_product_id' => $line['crm_product_id'] ?? null,
                'description' => $line['description'] ?? '',
                'quantity' => $line['quantity'] ?? '1',
                'unit_label' => $line['unit_label'] ?? '',
                'unit_price_rials' => $line['unit_price_rials'] ?? '0',
            ], $this->lines),
        ];
    }

    /** @return array{key: string, crm_product_id: null, description: string, quantity: string, unit_label: string, unit_price_rials: string} */
    private function emptyLine(string $description = ''): array
    {
        return [
            'key' => uniqid('line_', true),
            'crm_product_id' => null,
            'description' => $description,
            'quantity' => '1',
            'unit_label' => 'عدد',
            'unit_price_rials' => '',
        ];
    }

    private function shouldReplacePlaceholderLine(): bool
    {
        if (count($this->lines) !== 1) {
            return false;
        }

        $line = $this->lines[0];
        $description = trim((string) ($line['description'] ?? ''));
        $price = trim((string) ($line['unit_price_rials'] ?? ''));

        return ($price === '' || $price === '0')
            && ($description === '' || $description === 'قلم جدید');
    }

    private function parseFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', ',', '٬', ' '], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '', '', ''], (string) $value);
        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function parseInt(mixed $value): ?int
    {
        $float = $this->parseFloat($value);
        if ($float === null) {
            return null;
        }

        return (int) round($float);
    }
}
