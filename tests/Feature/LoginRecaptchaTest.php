<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Auth\Login as AdminLogin;
use App\Livewire\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LoginRecaptchaTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_pages_omit_the_widget_when_recaptcha_is_disabled(): void
    {
        config([
            'onprem.enabled' => false,
            'services.recaptcha.enabled' => false,
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret',
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('google.com/recaptcha', false)
            ->assertDontSee('test-site-key', false);

        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('google.com/recaptcha', false)
            ->assertDontSee('test-site-key', false);
    }

    public function test_login_pages_render_the_widget_when_recaptcha_is_enabled(): void
    {
        $this->enableRecaptcha();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('google.com/recaptcha', false)
            ->assertSee('test-site-key', false);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('google.com/recaptcha', false)
            ->assertSee('test-site-key', false);
    }

    public function test_on_prem_login_pages_omit_the_widget_even_when_the_flag_is_on(): void
    {
        $this->enableRecaptcha();
        config(['onprem.enabled' => true]);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('google.com/recaptcha', false);

        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee('google.com/recaptcha', false);
    }

    public function test_public_login_rejects_a_missing_captcha_when_enabled(): void
    {
        $this->enableRecaptcha();
        Http::fake();

        $user = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::test(Login::class)
            ->set('identifier', $user->email)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasErrors(['captcha']);

        Http::assertNothingSent();
        $this->assertGuest();
    }

    public function test_public_login_rejects_a_token_google_rejects(): void
    {
        $this->enableRecaptcha();
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $user = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::test(Login::class)
            ->set('identifier', $user->email)
            ->set('password', 'password')
            ->set('captcha', 'bad-token')
            ->call('authenticate')
            ->assertHasErrors(['captcha']);

        $this->assertGuest();
    }

    public function test_public_login_accepts_a_verified_token(): void
    {
        $this->enableRecaptcha();
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $user = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::test(Login::class)
            ->set('identifier', $user->email)
            ->set('password', 'password')
            ->set('captcha', 'good-token')
            ->call('authenticate')
            ->assertRedirect(url('/admin'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_login_rejects_a_token_google_rejects(): void
    {
        $this->enableRecaptcha();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $user = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::test(AdminLogin::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'password')
            ->set('data.captcha', 'bad-token')
            ->call('authenticate')
            ->assertHasErrors(['data.captcha']);

        $this->assertGuest();
    }

    public function test_admin_login_accepts_a_verified_token(): void
    {
        $this->enableRecaptcha();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $user = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::test(AdminLogin::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'password')
            ->set('data.captcha', 'good-token')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
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
