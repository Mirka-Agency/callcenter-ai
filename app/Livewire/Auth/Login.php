<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.auth')]
#[Title('ورود')]
class Login extends Component
{
    public string $identifier = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        $this->identifier = request()->query('p', '');
        $this->password = request()->query('s', '');
    }

    public function authenticate(): void
    {
        $this->validate([
            'identifier' => ['required'],
            'password' => ['required'],
        ]);

        $credentials = $this->resolveCredentials();

        if ($credentials === null || ! Auth::attempt($credentials, $this->remember)) {
            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }

        session()->regenerate();

        $this->redirect(auth()->user()->portalRoute(), navigate: true);
    }

    /** @return array{email: string, password: string}|null */
    private function resolveCredentials(): ?array
    {
        $identifier = trim($this->identifier);

        if (str_contains($identifier, '@')) {
            return ['email' => $identifier, 'password' => $this->password];
        }

        $user = User::query()->where('phone', $identifier)->first();

        if ($user === null) {
            return null;
        }

        return ['email' => $user->email, 'password' => $this->password];
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
