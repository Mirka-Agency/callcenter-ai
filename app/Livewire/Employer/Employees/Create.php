<?php

namespace App\Livewire\Employer\Employees;

use App\Enums\UserRole;
use App\Livewire\Employer\Concerns\ManagesEmployeeIntegrations;
use App\Livewire\Employer\Employees\Concerns\ManagesEmployeeAvatar;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\EmployerContext;
use App\Services\OrganizationActivityLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.employer')]
#[Title('افزودن کارشناس')]
class Create extends Component
{
    use ManagesEmployeeAvatar;
    use ManagesEmployeeIntegrations;
    use WithFileUploads;

    public function mount(): void
    {
        $this->bootEmployeeIntegrations();
    }

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $password = '';

    public ?string $mobile = null;

    public ?string $position = null;

    public ?string $department = null;

    public bool $is_active = true;

    public function save(): void
    {
        $data = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'mobile' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            ...$this->avatarValidationRules(),
        ]);

        $organizationId = EmployerContext::organizationId();

        $user = User::query()->create([
            'name' => trim("{$data['first_name']} {$data['last_name']}"),
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::Employee,
        ]);

        OrganizationUser::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'mobile' => $data['mobile'],
            'position' => $data['position'],
            'department' => $data['department'],
            'is_active' => $data['is_active'],
        ]);

        $membership = OrganizationUser::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->persistEmployeeIntegrations($membership);

        $this->persistAvatar($user);

        OrganizationActivityLogger::log(
            organizationId: $organizationId,
            type: 'employee_created',
            title: 'کارشناس جدید ثبت شد',
            description: "{$data['first_name']} {$data['last_name']} به تیم اضافه شد.",
        );

        session()->flash('status', __('ui.success.employee_created'));

        $this->redirect(route('employer.employees.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.employer.employees.form', ['employee' => null]);
    }
}
