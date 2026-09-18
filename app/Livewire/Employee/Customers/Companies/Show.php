<?php

namespace App\Livewire\Employee\Customers\Companies;

use App\Models\CustomerCompany;
use App\Services\CustomerCompanyIntelligenceService;
use App\Services\CustomerCompanyService;
use App\Services\EmployeeContext;
use App\Support\CustomerCompanyTenantGuard;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee')]
#[Title('پروفایل شرکت')]
class Show extends Component
{
    public CustomerCompany $customerCompany;

    public function mount(CustomerCompany $customerCompany): void
    {
        CustomerCompanyTenantGuard::assertCompanyInOrganization(
            $customerCompany,
            EmployeeContext::membership()->organization_id,
        );

        $this->customerCompany = $customerCompany->load([
            'contacts' => fn ($query) => $query->orderByDesc('last_contact_at'),
        ]);
    }

    public function render()
    {
        $intelligence = app(CustomerCompanyIntelligenceService::class);
        $membershipId = EmployeeContext::membership()->id;

        return view('livewire.shared.customers.companies.show', [
            'company' => $this->customerCompany,
            'analytics' => $intelligence->profileAnalytics($this->customerCompany),
            'timeline' => $intelligence->timeline($this->customerCompany),
            'employees' => $intelligence->assignedEmployees($this->customerCompany),
            'nextActions' => $intelligence->aggregatedNextActions($this->customerCompany),
            'summary' => app(CustomerCompanyService::class)->summaryStats($this->customerCompany),
            'portal' => 'employee',
            'indexRoute' => route('employee.customers.companies.index'),
            'hubRoute' => route('employee.customers.index'),
            'contactsListRoute' => route('employee.customers.contacts.index'),
            'companyEditRoute' => route('employee.customers.companies.edit', $this->customerCompany),
            'contactShowRouteName' => 'employee.customers.show',
            'analysisShowRoute' => 'employee.calls.show',
            'visibilityMode' => 'employee',
            'viewerMembershipId' => $membershipId,
            'isEmployer' => false,
        ]);
    }
}
