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
        $this->customer = $customer;
    }

    public function render()
    {
        $service = app(CustomerIntelligenceService::class);
        $membershipId = EmployeeContext::membership()->id;

        return view('livewire.employer.customers.show', [
            'analytics' => $service->profileAnalytics($this->customer),
            'timeline' => $service->timeline($this->customer),
            'employees' => $service->assignedEmployees($this->customer),
            'nextActions' => $service->aggregatedNextActions($this->customer),
            'visibilityMode' => 'employee',
            'viewerMembershipId' => $membershipId,
            'isEmployer' => false,
            'analysisShowRoute' => 'employee.calls.show',
        ]);
    }
}
