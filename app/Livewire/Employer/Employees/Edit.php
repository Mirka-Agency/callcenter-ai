<?php

namespace App\Livewire\Employer\Employees;

use App\Livewire\Employer\Concerns\ManagesEmployeeIntegrations;
use App\Livewire\Employer\Employees\Concerns\ManagesEmployeeAvatar;
use App\Models\OrganizationUser;
use App\Services\EmployerContext;
use App\Services\OrganizationActivityLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.employer')]
#[Title('ویرایش کارشناس')]
class Edit extends Component
{
    use ManagesEmployeeAvatar;
    use ManagesEmployeeIntegrations;
    use WithFileUploads;

    public OrganizationUser $employee;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public ?string $password = null;

    public ?string $mobile = null;

    public ?string $position = null;

    public ?string $department = null;

    public bool $is_active = true;

    public function mount(OrganizationUser $employee): void
    {
        abort_unless($employee->organization_id === EmployerContext::organizationId(), 404);

        $this->employee = $employee;
        $this->first_name = $employee->first_name;
        $this->last_name = $employee->last_name;
        $this->email = $employee->user?->email ?? '';
        $this->mobile = $employee->mobile;
        $this->position = $employee->position;
        $this->department = $employee->department;
        $this->is_active = $employee->is_active;

        $this->hydrateEmployeeIntegrationsFromMembership($employee);
    }

    public function save(): void
    {
        $data = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$this->employee->user_id],
            'password' => ['nullable', 'string', 'min:8'],
            'mobile' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            ...$this->avatarValidationRules(),
        ]);

        $this->employee->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'mobile' => $data['mobile'],
            'position' => $data['position'],
            'department' => $data['department'],
            'is_active' => $data['is_active'],
        ]);

        if ($this->employee->user) {
            $this->employee->user->update([
                'name' => trim("{$data['first_name']} {$data['last_name']}"),
                'email' => $data['email'],
                ...filled($data['password'] ?? null) ? ['password' => $data['password']] : [],
            ]);
        }

        if ($this->employee->user) {
            $this->persistAvatar($this->employee->user);
        }

        $this->persistEmployeeIntegrations($this->employee->fresh());

        OrganizationActivityLogger::log(
            organizationId: $this->employee->organization_id,
            type: 'employee_updated',
            title: 'اطلاعات کارشناس به‌روز شد',
            description: "پروفایل {$data['first_name']} {$data['last_name']} ذخیره شد.",
        );

        session()->flash('status', __('ui.success.employee_saved'));

        $this->redirect(route('employer.employees.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.employer.employees.form', ['employee' => $this->employee]);
    }
}
