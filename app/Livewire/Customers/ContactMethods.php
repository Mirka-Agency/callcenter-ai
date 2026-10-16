<?php

namespace App\Livewire\Customers;

use App\Application\Customer\Actions\AddCustomerContactMethod;
use App\Application\Customer\Actions\UpdateCustomerContactMethod;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerContactMethod;
use App\Services\EmployeeContext;
use App\Services\EmployerContext;
use App\Support\CustomerTenantGuard;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ContactMethods extends Component
{
    #[Locked]
    public int $customerId;

    public string $value = '';

    public string $label = '';

    public function mount(int $customerId): void
    {
        $this->customerId = $this->customer()->id;
    }

    public function add(AddCustomerContactMethod $action): void
    {
        try {
            $action->handle($this->customer(), $this->value, $this->label);
        } catch (ValidationException $exception) {
            $this->addError('value', $exception->validator->errors()->first());

            return;
        }

        $this->reset('value', 'label');
        session()->flash('contact_method_status', 'روش تماس اضافه شد.');
    }

    public function makePrimary(int $methodId, UpdateCustomerContactMethod $action): void
    {
        $this->mutate($methodId, fn (CustomerContactMethod $method) => $action->makePrimary($method), 'شماره اصلی به‌روز شد.');
    }

    public function deactivate(int $methodId, UpdateCustomerContactMethod $action): void
    {
        $this->mutate($methodId, fn (CustomerContactMethod $method) => $action->deactivate($method), 'روش تماس غیرفعال شد.');
    }

    public function render()
    {
        return view('livewire.customers.contact-methods', [
            'methods' => $this->customer()->contactMethods()->active()->orderByDesc('is_primary')->orderBy('type')->get(),
        ]);
    }

    private function mutate(int $methodId, callable $callback, string $status): void
    {
        $method = CustomerContactMethod::query()
            ->where('customer_id', $this->customer()->id)
            ->findOrFail($methodId);

        try {
            $callback($method);
        } catch (ValidationException $exception) {
            $this->addError('value', $exception->validator->errors()->first());

            return;
        }

        session()->flash('contact_method_status', $status);
    }

    private function customer(): Customer
    {
        $customer = Customer::query()->findOrFail($this->customerId);
        CustomerTenantGuard::assertCustomerInOrganization($customer, $this->organizationId());

        return $customer;
    }

    private function organizationId(): int
    {
        $role = auth()->user()?->role;

        if ($role === UserRole::Employee) {
            return EmployeeContext::organizationId();
        }

        return EmployerContext::organizationId();
    }
}
