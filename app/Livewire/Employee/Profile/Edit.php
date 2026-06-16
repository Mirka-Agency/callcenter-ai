<?php

namespace App\Livewire\Employee\Profile;

use App\Livewire\Employer\Employees\Concerns\ManagesEmployeeAvatar;
use App\Services\EmployeeContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.employee')]
#[Title('پروفایل من')]
class Edit extends Component
{
    use ManagesEmployeeAvatar;
    use WithFileUploads;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public ?string $password = null;

    public ?string $mobile = null;

    public ?string $position = null;

    public ?string $department = null;

    public function mount(): void
    {
        $membership = EmployeeContext::membership();
        $user = auth()->user();

        $this->first_name = $membership->first_name;
        $this->last_name = $membership->last_name;
        $this->email = $user->email;
        $this->mobile = $membership->mobile;
        $this->position = $membership->position;
        $this->department = $membership->department;
    }

    public function save(): void
    {
        $membership = EmployeeContext::membership();
        $user = auth()->user();

        $data = $this->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'mobile' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            ...$this->avatarValidationRules(),
        ]);

        $membership->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'mobile' => $data['mobile'],
            'position' => $data['position'],
            'department' => $data['department'],
        ]);

        $user->update([
            'name' => trim("{$data['first_name']} {$data['last_name']}"),
            'email' => $data['email'],
            ...filled($data['password'] ?? null) ? ['password' => $data['password']] : [],
        ]);

        $this->persistAvatar($user);

        $this->js("window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: 'پروفایل به‌روزرسانی شد.' } }))");
    }

    public function render()
    {
        return view('livewire.shared.profile.edit', [
            'portal' => 'employee',
            'backRoute' => route('employee.dashboard'),
        ]);
    }
}
