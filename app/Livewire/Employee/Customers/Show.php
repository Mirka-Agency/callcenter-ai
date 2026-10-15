<?php

namespace App\Livewire\Employee\Customers;

use App\Application\Crm\Deals\DealQuery;
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
        $this->customer = $customer->load(['company', 'owner']);
    }

    public function render()
    {
        $service = app(CustomerIntelligenceService::class);
        $membership = EmployeeContext::membership();
        $membershipId = $membership->id;
        $internalCrm = $membership->organization->usesInternalCrm();

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
            'canAssignOwner' => false,
            'canChangeLifecycle' => false,
            'lifecycleEvents' => $this->customer->lifecycleEvents()->latest('id')->limit(8)->get(),
            'relatedDeals' => $internalCrm
                ? app(DealQuery::class)->forCustomer($membership->organization_id, auth()->user(), $this->customer)
                : collect(),
            'dealShowRouteName' => $internalCrm ? 'employee.crm.deals.show' : null,
            'dealsIndexRoute' => $internalCrm ? route('employee.crm.deals.index', ['customer' => $this->customer->id]) : null,
            'relatedTasks' => $internalCrm
                ? \App\Models\CrmTask::query()
                    ->where('organization_id', $membership->organization_id)
                    ->where('customer_id', $this->customer->id)
                    ->where('assignee_organization_user_id', $membershipId)
                    ->with('assignee')
                    ->orderByRaw('due_at is null')
                    ->orderBy('due_at')
                    ->limit(15)
                    ->get()
                : collect(),
            'taskShowRouteName' => $internalCrm ? 'employee.crm.tasks.show' : null,
            'tasksIndexRoute' => $internalCrm ? route('employee.crm.tasks.index', ['customer' => $this->customer->id]) : null,
        ]);
    }
}
