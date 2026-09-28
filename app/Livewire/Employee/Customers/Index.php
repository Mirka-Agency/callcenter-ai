<?php

namespace App\Livewire\Employee\Customers;

use App\Models\Call;
use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Services\EmployeeContext;
use App\Services\Reports\DefinedExtensionCallConstraint;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee')]
#[Title('مشتریان')]
class Index extends Component
{
    public function render()
    {
        $organization = EmployeeContext::organization();
        $organizationId = $organization->id;

        $stats = [
            'companies' => CustomerCompany::query()->forOrganization($organizationId)->excludingOwnOrganization($organization)->count(),
            'contacts' => Customer::query()->forOrganization($organizationId)->count(),
            'calls' => app(DefinedExtensionCallConstraint::class)->apply(
                Call::query()->where('organization_id', $organizationId)->whereNotNull('customer_id'),
                $organizationId,
            )->count(),
        ];

        $recentCompanies = CustomerCompany::query()
            ->forOrganization($organizationId)
            ->excludingOwnOrganization($organization)
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
            'portal' => 'employee',
        ]);
    }
}
