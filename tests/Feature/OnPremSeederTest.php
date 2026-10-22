<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use App\Models\VoipProvider;
use Database\Seeders\OnPremSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OnPremSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_on_prem_seeder_bootstraps_single_organization(): void
    {
        config([
            'onprem.admin_name' => 'Ops Admin',
            'onprem.admin_email' => 'ops@onprem.test',
            'onprem.admin_password' => 'admin-secret',
            'onprem.employer_name' => 'کارفرمای محلی',
            'onprem.employer_email' => 'boss@onprem.test',
            'onprem.employer_password' => 'employer-secret',
            'onprem.org_title' => 'شرکت آزمایشی',
            'onprem.employer_can_manage_integrations' => true,
        ]);

        $this->seed(OnPremSeeder::class);

        $this->assertSame(1, Organization::query()->where('is_demo', false)->count());
        $this->assertSame(0, Organization::query()->where('is_demo', true)->count());

        $admin = User::query()->where('email', 'ops@onprem.test')->first();
        $this->assertNotNull($admin);
        $this->assertSame(UserRole::SuperAdmin, $admin->role);
        $this->assertTrue(Hash::check('admin-secret', $admin->password));

        $employer = User::query()->where('email', 'boss@onprem.test')->first();
        $this->assertNotNull($employer);
        $this->assertSame(UserRole::Employer, $employer->role);
        $this->assertTrue(Hash::check('employer-secret', $employer->password));

        $organization = $employer->primaryOrganization();
        $this->assertNotNull($organization);
        $this->assertSame('شرکت آزمایشی', $organization->title);
        $this->assertTrue($organization->employer_can_manage_integrations);
        $this->assertFalse($organization->is_demo);

        $this->assertGreaterThan(0, VoipProvider::query()->count());
    }

    public function test_on_prem_seeder_rejects_identical_admin_and_employer_emails(): void
    {
        config([
            'onprem.admin_email' => 'same@onprem.test',
            'onprem.admin_password' => 'secret',
            'onprem.employer_email' => 'same@onprem.test',
            'onprem.employer_password' => 'secret',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ONPREM_ADMIN_EMAIL and ONPREM_EMPLOYER_EMAIL must differ.');

        $this->seed(OnPremSeeder::class);
    }
}
