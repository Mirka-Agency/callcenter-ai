<?php

namespace App\Livewire\Employee\Customers\Companies;

use App\Services\CustomerCompanyUpdateService;
use App\Services\EmployeeContext;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employee')]
#[Title('شرکت جدید')]
class Create extends Component
{
    public string $name = '';

    public string $industry = '';

    public string $website = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $notes = '';

    public function save(CustomerCompanyUpdateService $updater): void
    {
        try {
            $company = $updater->create(EmployeeContext::membership()->organization_id, [
                'name' => $this->name,
                'industry' => $this->industry,
                'website' => $this->website,
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
                'notes' => $this->notes,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        session()->flash('status', __('ui.success.company_created'));

        $this->redirect(route('employee.customers.companies.show', $company), navigate: true);
    }

    public function render()
    {
        return view('livewire.shared.customers.companies.create', [
            'backRoute' => route('employee.customers.companies.index'),
            'portal' => 'employee',
        ]);
    }
}
