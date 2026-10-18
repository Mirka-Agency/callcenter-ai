<?php

namespace App\Livewire\Employee\Customers;

use App\Livewire\Concerns\HasCustomerListPerPage;
use App\Livewire\Concerns\HasCustomerListSort;
use App\Livewire\Concerns\ManagesCustomerCreateDialog;
use App\Services\EmployeeContext;
use App\Support\CustomerListQuery;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.employee')]
#[Title('اشخاص و شرکت‌ها')]
class Index extends Component
{
    use HasCustomerListPerPage;
    use HasCustomerListSort;
    use ManagesCustomerCreateDialog;
    use WithPagination;

    public string $search = '';

    public string $ownerFilter = 'all';

    public string $specificOwnerId = '';

    public string $stageFilter = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOwnerFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSpecificOwnerId(): void
    {
        $this->resetPage();
    }

    public function updatedStageFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $organization = EmployeeContext::organization();
        $organizationId = $organization->id;
        $specificOwnerId = ctype_digit($this->specificOwnerId) ? (int) $this->specificOwnerId : null;
        $membershipId = EmployeeContext::membership()->id;

        return view('livewire.shared.customers.hub', [
            'contacts' => CustomerListQuery::contacts(
                $organizationId,
                $this->search,
                $this->sort,
                $this->ownerFilter,
                $membershipId,
                $specificOwnerId,
                $this->stageFilter,
            )->paginate($this->currentPerPage()),
            'portal' => 'employee',
            'stats' => CustomerListQuery::directoryStats($organizationId, $organization),
            'ownerChoices' => CustomerListQuery::ownerChoices($organizationId),
            'lifecycleCounts' => CustomerListQuery::lifecycleCounts(
                $organizationId,
                $this->search,
                $this->ownerFilter,
                $membershipId,
                $specificOwnerId,
            ),
        ]);
    }
}
