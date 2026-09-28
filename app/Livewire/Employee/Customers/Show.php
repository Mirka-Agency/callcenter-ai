<?php

namespace App\Livewire\Employee\Customers;

use App\Models\Customer;
use App\Services\CustomerIntelligenceService;
use App\Services\EmployeeContext;
use App\Support\CustomerTenantGuard;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee')]
#[Title('پروفایل مشتری')]
class Show extends Component
{
    public Customer $customer;

    public function mount(Customer $customer): void
    {
        CustomerTenantGuard::assertCustomerInOrganization(
            $customer,
            EmployeeContext::membership()->organization_id,
        );
        $this->customer = $customer->load('company');
    }

    public function render()
    {
        $service = app(CustomerIntelligenceService::class);
        $membershipId = EmployeeContext::membership()->id;

        return view('livewire.employer.customers.show', [
            'analytics' => $service->profileAnalytics($this->customer),
            'timeline' => $service->timeline($this->customer),
            'timelineTotal' => $service->timelineCount($this->customer),
            'employees' => $service->assignedEmployees($this->customer),
            'nextActions' => $service->aggregatedNextActions($this->customer),
            'visibilityMode' => 'employee',
            'viewerMembershipId' => $membershipId,
            'isEmployer' => false,
            'analysisShowRoute' => 'employee.calls.show',
            'companyShowRouteName' => 'employee.customers.companies.show',
            'customerEditRouteName' => 'employee.customers.edit',
            'customersIndexRoute' => route('employee.customers.contacts.index'),
            'customersHubRoute' => route('employee.customers.index'),
            'companiesListRoute' => route('employee.customers.companies.index'),
        ]);
    }
}
