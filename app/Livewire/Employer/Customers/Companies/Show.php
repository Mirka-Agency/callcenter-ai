<?php

namespace App\Livewire\Employer\Customers\Companies;

use App\Models\CustomerCompany;
use App\Services\CustomerCompanyIntelligenceService;
use App\Services\CustomerCompanyService;
use App\Services\EmployerContext;
use App\Support\CustomerCompanyTenantGuard;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('پروفایل شرکت')]
class Show extends Component
{
    public CustomerCompany $customerCompany;

    public function mount(CustomerCompany $customerCompany): void
    {
        CustomerCompanyTenantGuard::assertCompanyInOrganization(
            $customerCompany,
            EmployerContext::organizationId(),
        );

        $this->customerCompany = $customerCompany->load([
            'contacts' => fn ($query) => $query->orderByDesc('last_contact_at'),
        ]);
    }

    public function render()
    {
        $intelligence = app(CustomerCompanyIntelligenceService::class);

        return view('livewire.shared.customers.companies.show', [
            'company' => $this->customerCompany,
            'analytics' => $intelligence->profileAnalytics($this->customerCompany),
            'timeline' => $intelligence->timeline($this->customerCompany),
            'employees' => $intelligence->assignedEmployees($this->customerCompany),
            'nextActions' => $intelligence->aggregatedNextActions($this->customerCompany),
            'summary' => app(CustomerCompanyService::class)->summaryStats($this->customerCompany),
            'portal' => 'employer',
            'indexRoute' => route('employer.customers.companies.index'),
            'hubRoute' => route('employer.customers.index'),
            'contactsListRoute' => route('employer.customers.contacts.index'),
            'companyEditRoute' => route('employer.customers.companies.edit', $this->customerCompany),
            'contactShowRouteName' => 'employer.customers.show',
            'analysisShowRoute' => 'employer.intelligence.show',
            'visibilityMode' => 'full',
            'viewerMembershipId' => null,
            'isEmployer' => true,
        ]);
    }
}
