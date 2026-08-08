<?php

namespace App\Livewire\Employer\Profile;

use App\Livewire\Employer\Employees\Concerns\ManagesEmployeeAvatar;
use App\Services\EmployerContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.employer')]
#[Title('پروفایل من')]
class Edit extends Component
{
    use ManagesEmployeeAvatar;
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public ?string $password = null;

    public string $organization_title = '';

    public string $organization_business_context = '';

    public function mount(): void
    {
        $user = auth()->user();
        $organization = EmployerContext::organization();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->organization_title = $organization->title;
        $this->organization_business_context = $organization->business_context ?? '';
    }

    public function save(): void
    {
        $user = auth()->user();
        $organization = EmployerContext::organization();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'organization_title' => ['required', 'string', 'max:255'],
            'organization_business_context' => ['nullable', 'string', 'max:5000'],
            ...$this->avatarValidationRules(),
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            ...filled($data['password'] ?? null) ? ['password' => $data['password']] : [],
        ]);

        $organization->update([
            'title' => $data['organization_title'],
            'business_context' => filled($data['organization_business_context'] ?? null)
                ? $data['organization_business_context']
                : null,
        ]);

        $this->persistAvatar($user);

        $this->js("window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: '".__('ui.success.profile_saved')."' } }))");
    }

    public function render()
    {
        return view('livewire.shared.profile.edit', [
            'portal' => 'employer',
            'backRoute' => route('employer.dashboard'),
        ]);
    }
}
