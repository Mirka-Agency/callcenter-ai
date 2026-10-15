<?php

namespace App\Livewire\Crm\Invoices;

use App\Application\Crm\Invoices\Actions\CreateInvoice;
use App\Application\Crm\Invoices\InvoiceAuthorization;
use App\Application\Crm\Invoices\InvoiceQuery;
use App\Domain\Crm\Invoices\InvoiceDocumentType;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\Customer;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('فاکتور و پیش فاکتور')]
class Index extends Component
{
    use ResolvesCrmContext;
    use WithPagination;

    #[Url(except: 'all')]
    public string $type = 'all';

    public bool $showCreate = false;

    public string $documentType = 'proforma';

    public string $customerId = '';

    public function setType(string $type): void
    {
        $this->type = in_array($type, ['all', 'proforma', 'invoice'], true) ? $type : 'all';
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->documentType = InvoiceDocumentType::Proforma->value;
        $this->customerId = '';
        $this->resetErrorBag();
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
        $this->reset(['documentType', 'customerId']);
        $this->documentType = InvoiceDocumentType::Proforma->value;
        $this->resetErrorBag();
    }

    public function createDraft(CreateInvoice $action): void
    {
        $invoice = $action->handle($this->crmOrganizationId(), $this->crmUser(), [
            'document_type' => $this->documentType,
            'customer_id' => $this->customerId !== '' ? (int) $this->customerId : null,
        ]);

        $this->closeCreate();
        $this->toast('پیش‌نویس سند ساخته شد.');
        $this->redirect(route($this->crmPortal().'.crm.invoices.show', $invoice), navigate: true);
    }

    public function render(InvoiceQuery $query, InvoiceAuthorization $auth)
    {
        $organizationId = $this->crmOrganizationId();
        $actor = $this->crmUser();
        $filters = ['type' => $this->type];

        $customers = Customer::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'name', 'phone_number', 'company_name']);

        return view('livewire.crm.invoices.index', [
            'invoices' => $query->paginate($organizationId, $actor, $filters),
            'counts' => $query->counts($organizationId, $actor, $filters),
            'customers' => $customers,
            'canManage' => $auth->canManage($actor, $organizationId),
            'portal' => $this->crmPortal(),
        ])->layout($this->crmLayout());
    }
}
