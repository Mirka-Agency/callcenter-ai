<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationCrmConnection;
use App\Models\OrganizationVoipConnection;
use App\Models\OrganizationWallet;
use App\Models\OrganizationActivity;
use App\Models\User;
use App\Services\Demo\DemoEmployeeProvisioner;
use App\Services\EmployerDashboardAnalytics;
use App\Services\Reports\OrganizationCallMetrics;
use App\Support\Seeding\DemoCatalog;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PlatformFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_organizations_users_and_analytics(): void
    {
        $this->seed(PlatformFoundationSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(DemoCatalog::ORGANIZATION_COUNT, Organization::query()->count());

        $demoUsers = User::query()
            ->where('email', 'like', '%@'.DemoCatalog::EMAIL_DOMAIN)
            ->count();

        $this->assertSame(
            DemoCatalog::ORGANIZATION_COUNT * (1 + DemoCatalog::EMPLOYEES_PER_ORGANIZATION),
            $demoUsers,
        );

        $this->assertSame(
            DemoCatalog::ORGANIZATION_COUNT,
            User::query()->where('role', UserRole::Employer)->where('email', 'like', '%@'.DemoCatalog::EMAIL_DOMAIN)->count(),
        );
        $this->assertSame(
            DemoCatalog::ORGANIZATION_COUNT * DemoCatalog::EMPLOYEES_PER_ORGANIZATION,
            User::query()->where('role', UserRole::Employee)->where('email', 'like', '%@'.DemoCatalog::EMAIL_DOMAIN)->count(),
        );

        $employer = User::query()->where('email', DemoCatalog::exampleEmployerLogin())->first();
        $this->assertNotNull($employer);
        $this->assertSame('mohsen.rezaei@gmail.com', $employer->email);

        $employee = User::query()->where('email', 'ali.mohammadi@gmail.com')->first();
        $this->assertNotNull($employee);
        $this->assertTrue(Hash::check(DemoCatalog::DEMO_PASSWORD, $employee->password));
        $this->assertContains($employee->avatar_path, DemoCatalog::MALE_AVATARS);
        $this->assertSame($employee->avatar_path, $employee->avatarUrl());

        User::query()
            ->where('email', 'like', '%@'.DemoCatalog::EMAIL_DOMAIN)
            ->each(function (User $user): void {
                $this->assertNotNull($user->avatar_path);
                $this->assertMatchesRegularExpression('#^/img/avatar[1-4]\.webp$#', $user->avatar_path);
                $this->assertNotNull($user->avatarUrl());
            });

        Organization::query()->each(function (Organization $organization): void {
            $employeeAvatars = User::query()
                ->whereIn('id', $organization->memberships()->pluck('user_id'))
                ->pluck('avatar_path')
                ->all();

            $this->assertCount(DemoCatalog::EMPLOYEES_PER_ORGANIZATION, $employeeAvatars);
            $this->assertSame(
                count($employeeAvatars),
                count(array_unique($employeeAvatars)),
                'Each employee in an organization should have a distinct avatar.',
            );

            $this->assertTrue($organization->is_demo);
            $this->assertSame(0, OrganizationVoipConnection::query()->where('organization_id', $organization->id)->count());
            $this->assertSame(0, OrganizationCrmConnection::query()->where('organization_id', $organization->id)->count());
            $wallet = OrganizationWallet::query()->where('organization_id', $organization->id)->first();
            $this->assertNotNull($wallet);
            $this->assertSame((float) DemoCatalog::WALLET_BALANCE_IRR, (float) $wallet->balance);
            $this->assertGreaterThanOrEqual(DemoCatalog::CUSTOMERS_PER_ORGANIZATION, Customer::query()->where('organization_id', $organization->id)->count());
            $this->assertSame(DemoCatalog::CALLS_PER_ORGANIZATION, Call::query()->where('organization_id', $organization->id)->count());
            $this->assertSame(
                DemoCatalog::CALLS_TODAY_PER_ORGANIZATION,
                app(OrganizationCallMetrics::class)->countToday($organization->id),
            );
            $this->assertSame(
                DemoCatalog::CALLS_TODAY_PER_ORGANIZATION,
                EmployerDashboardAnalytics::forOrganization($organization->id)->cockpit()['calls_today'],
            );
            $this->assertSame(DemoCatalog::CALLS_PER_ORGANIZATION, ConversationAnalysis::query()->where('organization_id', $organization->id)->count());
            $this->assertNotEmpty(EmployerDashboardAnalytics::forOrganization($organization->id)->activityFeed(6));
            $this->assertGreaterThan(0, OrganizationActivity::query()->where('organization_id', $organization->id)->count());

            $sampleAnalysis = ConversationAnalysis::query()
                ->where('organization_id', $organization->id)
                ->whereNotNull('transcript')
                ->first();

            $this->assertNotNull($sampleAnalysis);
            $this->assertNotNull($sampleAnalysis->customer_insights_json);
            $this->assertNotNull($sampleAnalysis->operational_insights_json);
            $this->assertArrayHasKey('communication_skills', $sampleAnalysis->performance_dimensions_json ?? []);
        });
    }

    public function test_demo_seeder_is_idempotent_for_organizations(): void
    {
        $this->seed(PlatformFoundationSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(DemoCatalog::ORGANIZATION_COUNT, Organization::query()->count());
    }

    public function test_demo_employee_provisioner_adds_another_user_with_unique_email(): void
    {
        $this->seed(PlatformFoundationSeeder::class);
        $this->seed(DemoSeeder::class);

        $organization = Organization::query()->where('title', 'مرکز تماس پارسین')->firstOrFail();
        $countBefore = $organization->memberships()->count();

        $membership = app(DemoEmployeeProvisioner::class)->provision($organization);

        $this->assertSame($countBefore + 1, $organization->fresh()->memberships()->count());
        $this->assertTrue(DemoCatalog::isDemoUserEmail($membership->user->email));
        $this->assertTrue(Hash::check(DemoCatalog::DEMO_PASSWORD, $membership->user->password));
        $this->assertMatchesRegularExpression('/^[a-z]+\.[a-z]+(\.\d+)?@gmail\.com$/', $membership->user->email);
        $this->assertContains($membership->user->avatar_path, DemoCatalog::MALE_AVATARS);
    }
}
