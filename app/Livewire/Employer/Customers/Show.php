<?php

namespace App\Livewire\Employer\Customers;

use App\Models\Customer;
use App\Services\CustomerIntelligenceService;
use App\Services\EmployerContext;
use App\Support\CustomerTenantGuard;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('پروفایل مشتری')]
class Show extends Component
{
    public Customer $customer;

    public function mount(Customer $customer): void
    {
        CustomerTenantGuard::assertCustomerInOrganization($customer, EmployerContext::organizationId());
        $this->customer = $customer;
    }

    public function render()
    {
        $service = app(CustomerIntelligenceService::class);

        return view('livewire.employer.customers.show', [
            'analytics' => $service->profileAnalytics($this->customer),
            'timeline' => $service->timeline($this->customer),
            'employees' => $service->assignedEmployees($this->customer),
            'nextActions' => $service->aggregatedNextActions($this->customer),
            'visibilityMode' => 'full',
            'viewerMembershipId' => null,
            'analysisShowRoute' => 'employer.intelligence.show',
            'isEmployer' => true,
        ]);
    }
}
