<?php

namespace App\Livewire\Employee\Customers;

use App\Models\Customer;
use App\Models\CustomerCompany;
use App\Services\CustomerProfileUpdateService;
use App\Services\EmployeeContext;
use App\Support\CustomerTenantGuard;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee')]
#[Title('ویرایش شخص')]
class Edit extends Component
{
    public Customer $customer;

    public string $name = '';

    public ?int $customer_company_id = null;

    public string $company_name = '';

    public string $phone_number = '';

    public string $email = '';

    public string $job_title = '';

    public function mount(Customer $customer): void
    {
        CustomerTenantGuard::assertCustomerInOrganization(
            $customer,
            EmployeeContext::membership()->organization_id,
        );
        $this->customer = $customer;

        $this->name = $customer->name ?? '';
        $this->customer_company_id = $customer->customer_company_id;
        $this->company_name = $customer->company_name ?? '';
        $this->phone_number = $customer->phone_number ?? '';
        $this->email = $customer->email ?? '';
        $this->job_title = $customer->job_title ?? '';
    }

    public function save(CustomerProfileUpdateService $updater): void
    {
        try {
            $this->customer = $updater->update($this->customer, [
                'name' => $this->name,
                'customer_company_id' => $this->customer_company_id,
                'company_name' => $this->company_name,
                'phone_number' => $this->phone_number,
                'email' => $this->email,
                'job_title' => $this->job_title,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        session()->flash('status', __('ui.success.customer_saved'));

        $this->redirect(route('employee.customers.show', $this->customer), navigate: true);
    }

    public function render()
    {
        $organizationId = EmployeeContext::membership()->organization_id;

        return view('livewire.shared.customers.edit', [
            'backRoute' => route('employee.customers.show', $this->customer),
            'companies' => CustomerCompany::query()
                ->forOrganization($organizationId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'portal' => 'employee',
        ]);
    }
}
