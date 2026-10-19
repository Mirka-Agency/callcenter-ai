<?php

namespace App\Livewire\Employee\Customers\Calls;

use App\Livewire\Concerns\HasCustomerListPerPage;
use App\Livewire\Concerns\ManagesCustomerCreateDialog;
use App\Services\EmployeeContext;
use App\Support\CustomerListQuery;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.employee')]
#[Title('تماس‌ها')]
class Index extends Component
{
    use HasCustomerListPerPage;
    use ManagesCustomerCreateDialog;
    use WithPagination;

    public string $search = '';

    public string $direction = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDirection(): void
    {
        $this->direction = in_array($this->direction, ['all', 'inbound', 'outbound'], true)
            ? $this->direction
            : 'all';
        $this->resetPage();
    }

    public function render()
    {
        $organization = EmployeeContext::organization();
        $organizationId = $organization->id;

        return view('livewire.shared.customers.calls.index', [
            'calls' => CustomerListQuery::calls($organizationId, $this->search, $this->direction)
                ->paginate($this->currentPerPage()),
            'portal' => 'employee',
            'stats' => CustomerListQuery::directoryStats($organizationId, $organization),
        ]);
    }
}
