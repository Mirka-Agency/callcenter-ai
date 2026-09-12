<?php

namespace App\Livewire\Employer\Customers\Companies;

use App\Livewire\Concerns\HasCustomerListSort;
use App\Models\CustomerCompany;
use App\Services\EmployerContext;
use App\Support\CustomerListSort;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.employer')]
#[Title('سازمان‌ها')]
class Index extends Component
{
    use HasCustomerListSort;
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $organizationId = EmployerContext::organizationId();

        $companies = CustomerCompany::query()
            ->forOrganization($organizationId)
            ->with(['contacts' => fn ($query) => $query->orderByDesc('last_contact_at')->limit(4)])
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('industry', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            });

        CustomerListSort::apply($companies, $this->sort, 'company', $organizationId);

        $companies = $companies->paginate(12);

        return view('livewire.shared.customers.companies.index', [
            'companies' => $companies,
            'portal' => 'employer',
        ]);
    }
}
