<?php

namespace App\Filament\Auth;

use App\Support\Recaptcha;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
                ...$this->getRecaptchaFormComponent(),
            ]);
    }

    public function authenticate(): ?LoginResponse
    {
        if (Recaptcha::enabled() && blank($this->userUndertakingMultiFactorAuthentication)) {
            $token = $this->data['captcha'] ?? null;
            $captchaOk = is_string($token) && $token !== '' && Recaptcha::verify($token);

            try {
                $this->form->getState();
            } catch (ValidationException $exception) {
                if (! $captchaOk) {
                    $this->resetRecaptcha();

                    throw ValidationException::withMessages([
                        ...$exception->errors(),
                        'data.captcha' => __('auth.recaptcha'),
                    ]);
                }

                throw $exception;
            }

            if (! $captchaOk) {
                $this->resetRecaptcha();

                throw ValidationException::withMessages([
                    'data.captcha' => __('auth.recaptcha'),
                ]);
            }
        }

        try {
            return parent::authenticate();
        } catch (ValidationException $exception) {
            $this->resetRecaptcha();

            throw $exception;
        }
    }

    /**
     * @return array<int, View>
     */
    protected function getRecaptchaFormComponent(): array
    {
        if (! Recaptcha::enabled()) {
            return [];
        }

        return [
            View::make('auth.recaptcha')
                ->viewData([
                    'statePath' => 'data.captcha',
                ]),
        ];
    }

    private function resetRecaptcha(): void
    {
        if (! Recaptcha::enabled()) {
            return;
        }

        $this->data['captcha'] = '';
        $this->js('window.grecaptcha && window.grecaptcha.reset()');
    }
}
