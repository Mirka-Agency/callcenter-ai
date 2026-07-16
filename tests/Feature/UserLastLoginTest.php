<?php

namespace Tests\Feature;

use App\Application\Impersonation\Actions\StartImpersonationAction;
use App\Enums\UserRole;
use App\Livewire\Auth\Login;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class UserLastLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_records_last_login_at(): void
    {
        $employer = User::factory()->employer()->create([
            'last_login_at' => null,
        ]);
        Organization::factory()->create(['user_id' => $employer->id]);

        Livewire::test(Login::class)
            ->set('identifier', $employer->email)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('employer.dashboard'));

        $employer->refresh();

        $this->assertNotNull($employer->last_login_at);
        $this->assertTrue($employer->last_login_at->greaterThanOrEqualTo(now()->subMinute()));
    }

    public function test_employee_login_records_last_login_at(): void
    {
        $organization = Organization::factory()->create();
        $employee = User::factory()->employee()->create([
            'last_login_at' => null,
        ]);

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

        $employee->refresh();

        $this->assertNotNull($employee->last_login_at);
    }

    public function test_impersonation_does_not_update_target_last_login_at(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $employer = User::factory()->employer()->create([
            'last_login_at' => now()->subDays(3),
        ]);
        Organization::factory()->create(['user_id' => $employer->id]);

        $originalLastLogin = $employer->last_login_at->copy();

        Auth::login($admin);

        app(StartImpersonationAction::class)->execute(
            admin: $admin,
            target: $employer,
            request: request(),
        );

        $employer->refresh();

        $this->assertAuthenticatedAs($employer);
        $this->assertTrue($employer->last_login_at->equalTo($originalLastLogin));
    }
}
