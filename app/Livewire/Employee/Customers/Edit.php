<?php

namespace App\Livewire\Employee\Customers;

use App\Models\Customer;
use App\Services\CustomerProfileUpdateService;
use App\Services\EmployeeContext;
use App\Support\CustomerTenantGuard;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee')]
#[Title('ویرایش مشتری')]
class Edit extends Component
{
    public Customer $customer;

    public string $name = '';

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

        session()->flash('status', 'اطلاعات مشتری به‌روزرسانی شد.');

        $this->redirect(route('employee.customers.show', $this->customer), navigate: true);
    }

    public function render()
    {
        return view('livewire.shared.customers.edit', [
            'backRoute' => route('employee.customers.show', $this->customer),
        ]);
    }
}
