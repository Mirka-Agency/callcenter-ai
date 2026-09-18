<?php

namespace App\Livewire\Employee\Customers\Contacts;

use App\Livewire\Concerns\HasCustomerListPerPage;
use App\Livewire\Concerns\HasCustomerListSort;
use App\Services\EmployeeContext;
use App\Support\CustomerListQuery;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.employee')]
#[Title('اشخاص')]
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
        $contacts = CustomerListQuery::contacts(
            EmployeeContext::organizationId(),
            $this->search,
            $this->sort,
        )->paginate($this->currentPerPage());

        return view('livewire.shared.customers.contacts.index', [
            'contacts' => $contacts,
            'portal' => 'employee',
        ]);
    }
}
