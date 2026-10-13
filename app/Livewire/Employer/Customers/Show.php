<?php

namespace App\Livewire\Employer\Customers;

use App\Application\Customer\Actions\AssignCustomerOwner;
use App\Application\Customer\Actions\ChangeCustomerLifecycle;
use App\Models\Customer;
use App\Services\CustomerIntelligenceService;
use App\Services\EmployerContext;
use App\Support\CustomerListQuery;
use App\Support\CustomerTenantGuard;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('پروفایل مشتری')]
class Show extends Component
{
    public Customer $customer;

    public string $ownerOrganizationUserId = '';

    public string $expectedOwnerOrganizationUserId = '';

    public string $lifecycleStage = '';

    public string $expectedLifecycleStage = '';

    public bool $confirmCustomerStage = false;

    public function mount(Customer $customer): void
    {
        CustomerTenantGuard::assertCustomerInOrganization($customer, EmployerContext::organizationId());
        $this->customer = $customer->load(['company', 'owner']);
        $this->syncOwnerFields();
        $this->syncLifecycleFields();
    }

    public function saveOwner(AssignCustomerOwner $owners): void
    {
        CustomerTenantGuard::assertCustomerInOrganization($this->customer, EmployerContext::organizationId());

        $expected = $this->expectedOwnerOrganizationUserId === ''
            ? null
            : (int) $this->expectedOwnerOrganizationUserId;
        $newOwner = $this->ownerOrganizationUserId === ''
            ? null
            : (int) $this->ownerOrganizationUserId;

        $updated = $owners->assign(
            customer: $this->customer,
            newOwnerOrganizationUserId: $newOwner,
            actorUserId: auth()->id(),
            source: 'manual',
            reason: 'profile',
            expectedOwnerOrganizationUserId: $expected,
            checkExpected: true,
        );

        $this->customer = $updated->load(['company', 'owner']);
        $this->syncOwnerFields();

        $this->js("window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: 'مسئول مشتری ثبت شد.' } }))");
    }

    public function saveLifecycle(ChangeCustomerLifecycle $lifecycle): void
    {
        CustomerTenantGuard::assertCustomerInOrganization($this->customer, EmployerContext::organizationId());

        $updated = $lifecycle->change(
            customer: $this->customer,
            toStage: $this->lifecycleStage === '' ? null : $this->lifecycleStage,
            actorUserId: auth()->id(),
            source: 'manual',
            reason: 'profile',
            expectedStage: $this->expectedLifecycleStage === '' ? null : $this->expectedLifecycleStage,
            checkExpected: true,
            confirmed: $this->confirmCustomerStage,
        );

        $this->customer = $updated->load(['company', 'owner']);
        $this->syncLifecycleFields();
        $this->confirmCustomerStage = false;

        $this->js("window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: 'مرحله چرخه عمر ثبت شد.' } }))");
    }

    public function render()
    {
        $service = app(CustomerIntelligenceService::class);

        return view('livewire.employer.customers.show', [
            'analytics' => $service->profileAnalytics($this->customer),
            'timeline' => $service->timeline($this->customer),
            'timelineTotal' => $service->timelineCount($this->customer),
            'employees' => $service->assignedEmployees($this->customer),
            'nextActions' => $service->aggregatedNextActions($this->customer),
            'visibilityMode' => 'full',
            'viewerMembershipId' => null,
            'analysisShowRoute' => 'employer.intelligence.show',
            'isEmployer' => true,
            'companyShowRouteName' => 'employer.customers.companies.show',
            'customerEditRouteName' => 'employer.customers.edit',
            'customersIndexRoute' => route('employer.customers.contacts.index'),
            'customersHubRoute' => route('employer.customers.index'),
            'companiesListRoute' => route('employer.customers.companies.index'),
            'canAssignOwner' => true,
            'ownerChoices' => CustomerListQuery::ownerChoices(EmployerContext::organizationId()),
            'canChangeLifecycle' => true,
            'lifecycleEvents' => $this->customer->lifecycleEvents()->latest('id')->limit(8)->get(),
        ]);
    }

    private function syncOwnerFields(): void
    {
        $ownerId = $this->customer->owner_organization_user_id;
        $this->ownerOrganizationUserId = $ownerId ? (string) $ownerId : '';
        $this->expectedOwnerOrganizationUserId = $this->ownerOrganizationUserId;
    }

    private function syncLifecycleFields(): void
    {
        $stage = $this->customer->lifecycle_stage?->value;
        $this->lifecycleStage = $stage ?? '';
        $this->expectedLifecycleStage = $this->lifecycleStage;
    }
}
