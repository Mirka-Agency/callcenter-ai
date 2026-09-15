<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Auth\Login;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnifiedAuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_home_redirects_to_login(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));
    }

    public function test_login_page_is_accessible(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('ورود');
    }

    public function test_legacy_employer_login_url_redirects_to_unified_login(): void
    {
        $this->get('/app/login')
            ->assertRedirect('/login');
    }

    public function test_legacy_employee_login_url_redirects_to_unified_login(): void
    {
        $this->get('/workspace/login')
            ->assertRedirect('/login');
    }

    public function test_employer_is_redirected_to_employer_dashboard_after_login(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        Organization::factory()->create(['user_id' => $employer->id]);

        $component = Livewire::test(Login::class)
            ->set('identifier', $employer->email)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('employer.dashboard'));

        $this->assertTrue($component->effects['redirectUsingNavigate'] ?? false);
        $this->assertAuthenticatedAs($employer);
    }

    public function test_employee_is_redirected_to_employee_dashboard_after_login(): void
    {
        $organization = Organization::factory()->create();
        $employee = User::factory()->create(['role' => UserRole::Employee]);

        OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->set('identifier', $employee->email)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('employee.dashboard'));

        $this->assertAuthenticatedAs($employee);
    }

    public function test_admin_is_redirected_to_admin_panel_after_login(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $component = Livewire::test(Login::class)
            ->set('identifier', $admin->email)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(url('/admin'));

        $this->assertArrayNotHasKey('redirectUsingNavigate', $component->effects);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_super_admin_is_redirected_to_admin_panel_without_spa_navigation(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $component = Livewire::test(Login::class)
            ->set('identifier', $admin->email)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(url('/admin'));

        $this->assertArrayNotHasKey('redirectUsingNavigate', $component->effects);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_invalid_credentials_show_persian_error(): void
    {
        Livewire::test(Login::class)
            ->set('identifier', 'missing@example.com')
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors(['identifier']);

        $this->assertGuest();
    }
}
