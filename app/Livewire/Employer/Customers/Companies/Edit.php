<?php

namespace App\Livewire\Employer\Customers\Companies;

use App\Models\CustomerCompany;
use App\Services\CustomerCompanyUpdateService;
use App\Services\EmployerContext;
use App\Support\CustomerCompanyTenantGuard;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('ویرایش شرکت')]
class Edit extends Component
{
    public CustomerCompany $customerCompany;

    public string $name = '';

    public string $industry = '';

    public string $website = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $notes = '';

    public function mount(CustomerCompany $customerCompany): void
    {
        CustomerCompanyTenantGuard::assertCompanyInOrganization(
            $customerCompany,
            EmployerContext::organizationId(),
        );

        $this->customerCompany = $customerCompany;
        $this->name = $customerCompany->name;
        $this->industry = $customerCompany->industry ?? '';
        $this->website = $customerCompany->website ?? '';
        $this->phone = $customerCompany->phone ?? '';
        $this->email = $customerCompany->email ?? '';
        $this->address = $customerCompany->address ?? '';
        $this->notes = $customerCompany->notes ?? '';
    }

    public function save(CustomerCompanyUpdateService $updater): void
    {
        try {
            $this->customerCompany = $updater->update($this->customerCompany, [
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

        session()->flash('status', __('ui.success.company_saved'));

        $this->redirect(route('employer.customers.companies.show', $this->customerCompany), navigate: true);
    }

    public function render()
    {
        return view('livewire.shared.customers.companies.edit', [
            'company' => $this->customerCompany,
            'backRoute' => route('employer.customers.companies.show', $this->customerCompany),
            'portal' => 'employer',
        ]);
    }
}
