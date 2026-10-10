<?php

namespace Tests\Unit;

use App\Support\Recaptcha;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecaptchaTest extends TestCase
{
    public function test_it_is_disabled_without_keys_even_when_the_flag_is_on(): void
    {
        config([
            'onprem.enabled' => false,
            'services.recaptcha.enabled' => true,
            'services.recaptcha.site_key' => '',
            'services.recaptcha.secret_key' => '',
        ]);

        $this->assertFalse(Recaptcha::enabled());
    }

    public function test_it_is_enabled_when_the_flag_is_on_and_both_keys_are_set(): void
    {
        $this->enableRecaptcha();

        $this->assertTrue(Recaptcha::enabled());
    }

    public function test_the_env_flag_turns_it_off(): void
    {
        $this->enableRecaptcha();
        config(['services.recaptcha.enabled' => false]);

        $this->assertFalse(Recaptcha::enabled());
    }

    public function test_on_prem_forces_it_off(): void
    {
        $this->enableRecaptcha();
        config(['onprem.enabled' => true]);

        $this->assertFalse(Recaptcha::enabled());
        $this->assertTrue(Recaptcha::verify(null));
    }

    public function test_verify_accepts_a_token_google_marks_successful(): void
    {
        $this->enableRecaptcha();
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $this->assertTrue(Recaptcha::verify('token-from-widget'));

        Http::assertSent(fn ($request): bool => $request['response'] === 'token-from-widget'
            && $request['secret'] === 'test-secret');
    }

    public function test_verify_rejects_a_missing_or_failed_token(): void
    {
        $this->enableRecaptcha();
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $this->assertFalse(Recaptcha::verify(null));
        $this->assertFalse(Recaptcha::verify('bad-token'));
    }

    private function enableRecaptcha(): void
    {
        config([
            'onprem.enabled' => false,
            'services.recaptcha.enabled' => true,
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret',
        ]);
    }
}
