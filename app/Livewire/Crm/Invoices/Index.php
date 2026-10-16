<?php

namespace App\Livewire\Crm\Invoices;

use App\Application\Crm\Invoices\Actions\CreateInvoice;
use App\Application\Crm\Invoices\InvoiceAuthorization;
use App\Application\Crm\Invoices\InvoiceQuery;
use App\Domain\Crm\Invoices\InvoiceDocumentType;
use App\Domain\Crm\Invoices\InvoiceStatus;
use App\Livewire\Concerns\HasCustomerListPerPage;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Models\Customer;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('اسناد فروش')]
class Index extends Component
{
    use HasCustomerListPerPage;
    use ResolvesCrmContext;
    use WithPagination;

    #[Url(except: 'all')]
    public string $tab = 'all';

    #[Url(except: 'all')]
    public string $type = 'all';

    #[Url(except: 'all')]
    public string $status = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'customer', except: '')]
    public string $customerIdFilter = '';

    #[Url(as: 'from', except: '')]
    public string $fromDate = '';

    public bool $showFilters = true;

    public bool $showCreate = false;

    public string $documentType = 'proforma';

    public string $customerId = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->type = in_array($this->type, ['all', 'proforma', 'invoice'], true) ? $this->type : 'all';
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->status = in_array($this->status, ['all', 'draft', 'issued'], true) ? $this->status : 'all';
        $this->resetPage();
    }

    public function updatedCustomerIdFilter(): void
    {
        $this->resetPage();
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['all', 'issued', 'drafts'], true) ? $tab : 'all';
        $this->resetPage();
    }

    public function setType(string $type): void
    {
        $this->type = in_array($type, ['all', 'proforma', 'invoice'], true) ? $type : 'all';
        $this->resetPage();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function openCreate(?string $documentType = null): void
    {
        $resolved = InvoiceDocumentType::tryFrom((string) $documentType);
        $this->documentType = ($resolved ?? InvoiceDocumentType::Proforma)->value;
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
        $filters = [
            'tab' => $this->tab,
            'type' => $this->type,
            'status' => $this->status,
            'search' => $this->search,
            'customer_id' => $this->customerIdFilter,
            'from_date' => $this->fromDate,
        ];

        $customers = Customer::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'name', 'phone_number', 'company_name']);

        return view('livewire.crm.invoices.index', [
            'invoices' => $query->paginate($organizationId, $actor, $filters, $this->currentPerPage()),
            'counts' => $query->counts($organizationId, $actor, $filters),
            'customers' => $customers,
            'canManage' => $auth->canManage($actor, $organizationId),
            'portal' => $this->crmPortal(),
            'statusOptions' => InvoiceStatus::cases(),
            'typeOptions' => InvoiceDocumentType::cases(),
        ])->layout($this->crmLayout());
    }
}
