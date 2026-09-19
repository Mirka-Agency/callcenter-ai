<?php

namespace App\Livewire\Employer\Customers\Contacts;

use App\Models\CustomerCompany;
use App\Services\CustomerProfileUpdateService;
use App\Services\EmployerContext;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('شخص جدید')]
class Create extends Component
{
    public string $name = '';

    public ?int $customer_company_id = null;

    public string $company_name = '';

    public string $phone_number = '';

    public string $email = '';

    public string $job_title = '';

    public function save(CustomerProfileUpdateService $updater): void
    {
        try {
            $customer = $updater->create(EmployerContext::organizationId(), [
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

        session()->flash('status', __('ui.success.customer_created'));

        $this->redirect(route('employer.customers.show', $customer), navigate: true);
    }

    public function render()
    {
        return view('livewire.shared.customers.create', [
            'backRoute' => route('employer.customers.contacts.index'),
            'companies' => CustomerCompany::query()
                ->forOrganization(EmployerContext::organizationId())
                ->orderBy('name')
                ->get(['id', 'name']),
            'portal' => 'employer',
        ]);
    }
}
