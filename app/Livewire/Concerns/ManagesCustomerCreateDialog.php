<?php

namespace App\Livewire\Concerns;

use App\Application\Customer\Actions\CreateCustomer;
use App\Models\CustomerCompany;
use App\Services\CustomerCompanyUpdateService;
use App\Services\EmployeeContext;
use App\Services\EmployerContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

trait ManagesCustomerCreateDialog
{
    public bool $showCreateDialog = false;

    public string $createMode = 'contact';

    public string $contactName = '';

    public string $jobTitle = '';

    public string $customerCompanyId = '';

    public string $linkedCompanyName = '';

    public string $phoneNumber = '';

    public string $contactEmail = '';

    public string $companyName = '';

    public string $industry = '';

    public string $website = '';

    public string $companyPhone = '';

    public string $companyEmail = '';

    public string $address = '';

    public string $notes = '';

    public function openCreateDialog(string $mode = 'contact'): void
    {
        $this->resetCreateForm();
        $this->setCreateMode($mode);
        $this->showCreateDialog = true;
    }

    public function closeCreateDialog(): void
    {
        $this->showCreateDialog = false;
        $this->resetErrorBag();
    }

    public function setCreateMode(string $mode): void
    {
        if (! in_array($mode, ['contact', 'company'], true)) {
            return;
        }

        $this->createMode = $mode;
        $this->resetErrorBag();
    }

    public function updatedCustomerCompanyId(): void
    {
        if ($this->customerCompanyId !== '') {
            $this->linkedCompanyName = '';
        }
    }

    public function saveCreateDialog(CreateCustomer $creator, CustomerCompanyUpdateService $companies): void
    {
        if ($this->createMode === 'company') {
            $this->saveCompanyFromDialog($companies);

            return;
        }

        $this->saveContactFromDialog($creator);
    }

    /** @return list<string> */
    public function industryOptions(): array
    {
        return [
            'فناوری',
            'خرده‌فروشی',
            'خدمات',
            'تولید',
            'آموزش',
            'سلامت',
            'مالی',
            'ساختمان',
            'حمل‌ونقل',
            'غذا و رستوران',
        ];
    }

    /** @return Collection<int, CustomerCompany> */
    public function createDialogCompanies(): Collection
    {
        return CustomerCompany::query()
            ->forOrganization($this->directoryOrganizationId())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function saveContactFromDialog(CreateCustomer $creator): void
    {
        try {
            $creator->handle($this->directoryOrganizationId(), [
                'name' => $this->contactName,
                'customer_company_id' => $this->customerCompanyId !== '' ? (int) $this->customerCompanyId : null,
                'company_name' => $this->linkedCompanyName,
                'phone_number' => $this->phoneNumber,
                'email' => $this->contactEmail,
                'job_title' => $this->jobTitle,
            ]);
        } catch (ValidationException $exception) {
            $this->applyCreateErrors($exception, [
                'name' => 'contactName',
                'customer_company_id' => 'customerCompanyId',
                'company_name' => 'linkedCompanyName',
                'phone_number' => 'phoneNumber',
                'email' => 'contactEmail',
                'job_title' => 'jobTitle',
            ]);

            return;
        }

        $this->finishCreate(__('ui.success.customer_created'));
    }

    private function saveCompanyFromDialog(CustomerCompanyUpdateService $companies): void
    {
        try {
            $companies->create($this->directoryOrganizationId(), [
                'name' => $this->companyName,
                'industry' => $this->industry,
                'website' => $this->website,
                'phone' => $this->companyPhone,
                'email' => $this->companyEmail,
                'address' => $this->address,
                'notes' => $this->notes,
            ]);
        } catch (ValidationException $exception) {
            $this->applyCreateErrors($exception, [
                'name' => 'companyName',
                'industry' => 'industry',
                'website' => 'website',
                'phone' => 'companyPhone',
                'email' => 'companyEmail',
                'address' => 'address',
                'notes' => 'notes',
            ]);

            return;
        }

        $this->finishCreate(__('ui.success.company_created'));
    }

    /** @param  array<string, string>  $map */
    private function applyCreateErrors(ValidationException $exception, array $map): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $this->addError($map[$field] ?? $field, $messages[0]);
        }
    }

    private function finishCreate(string $message): void
    {
        $this->resetCreateForm();
        $this->showCreateDialog = false;
        $this->resetPage();
        $this->js("window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: ".json_encode($message, JSON_UNESCAPED_UNICODE).' } }))');
    }

    private function resetCreateForm(): void
    {
        $this->reset([
            'contactName',
            'jobTitle',
            'customerCompanyId',
            'linkedCompanyName',
            'phoneNumber',
            'contactEmail',
            'companyName',
            'industry',
            'website',
            'companyPhone',
            'companyEmail',
            'address',
            'notes',
        ]);
        $this->resetErrorBag();
    }

    private function directoryOrganizationId(): int
    {
        if (str_starts_with(static::class, 'App\\Livewire\\Employee\\')) {
            return (int) EmployeeContext::membership()->organization_id;
        }

        return (int) EmployerContext::organizationId();
    }
}
