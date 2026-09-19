<?php

namespace App\Livewire\Employer\Customers;

use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Services\EmployerContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('مشتریان')]
class Index extends Component
{
    public function render()
    {
        $organizationId = EmployerContext::organizationId();

        $stats = [
            'companies' => CustomerCompany::query()->forOrganization($organizationId)->count(),
            'contacts' => Customer::query()->forOrganization($organizationId)->count(),
            'calls' => (int) Customer::query()->forOrganization($organizationId)->sum('total_calls'),
        ];

        $recentCompanies = CustomerCompany::query()
            ->forOrganization($organizationId)
            ->orderByDesc('last_contact_at')
            ->limit(4)
            ->get();

        $recentContacts = Customer::query()
            ->forOrganization($organizationId)
            ->with('company')
            ->orderByDesc('last_contact_at')
            ->limit(6)
            ->get();

        return view('livewire.shared.customers.hub', [
            'stats' => $stats,
            'recentCompanies' => $recentCompanies,
            'recentContacts' => $recentContacts,
            'portal' => 'employer',
        ]);
    }
}
