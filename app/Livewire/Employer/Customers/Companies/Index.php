<?php

namespace App\Livewire\Employer\Customers\Companies;

use App\Livewire\Concerns\HasCustomerListPerPage;
use App\Livewire\Concerns\HasCustomerListSort;
use App\Services\EmployerContext;
use App\Support\CustomerListQuery;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.employer')]
#[Title('شرکت‌ها')]
class Index extends Component
{
    use HasCustomerListPerPage;
    use HasCustomerListSort;
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $organization = EmployerContext::organization();
        $organizationId = $organization->id;
        $companies = CustomerListQuery::companies(
            $organizationId,
            $this->search,
            $this->sort,
        )->paginate($this->currentPerPage());

        return view('livewire.shared.customers.companies.index', [
            'companies' => $companies,
            'portal' => 'employer',
            'stats' => CustomerListQuery::directoryStats($organizationId, $organization),
        ]);
    }
}
