<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\Recaptcha;
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

    public string $captcha = '';

    public function mount(): void
    {
        $this->identifier = request()->query('p', '');
        $this->password = request()->query('s', '');
    }

    public function authenticate(): void
    {
        $rules = [
            'identifier' => ['required'],
            'password' => ['required'],
        ];

        if (Recaptcha::enabled()) {
            $rules['captcha'] = ['required', 'string'];
        }

        $this->validate($rules, [
            'captcha.required' => __('auth.recaptcha'),
        ]);

        if (Recaptcha::enabled() && ! Recaptcha::verify($this->captcha)) {
            $this->resetRecaptcha();

            throw ValidationException::withMessages([
                'captcha' => __('auth.recaptcha'),
            ]);
        }

        $credentials = $this->resolveCredentials();

        if ($credentials === null || ! Auth::attempt($credentials, $this->remember)) {
            $this->resetRecaptcha();

            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }

        session()->regenerate();

        $user = auth()->user();

        $this->redirect(
            $user->portalRoute(),
            navigate: ! $user->role->canAccessAdminPanel(),
        );
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

    private function resetRecaptcha(): void
    {
        if (! Recaptcha::enabled()) {
            return;
        }

        $this->reset('captcha');
        $this->js('window.grecaptcha && window.grecaptcha.reset()');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
