<?php

namespace Tests\Feature;

use App\Exceptions\DemoCleanupException;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use App\Services\Demo\DemoOrganizationCleanupService;
use App\Support\Seeding\DemoCatalog;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PlatformFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoOrganizationCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_single_demo_organization_and_related_data(): void
    {
        $this->seedDemoData();

        $organization = Organization::query()->demo()->firstOrFail();
        $organizationId = $organization->id;
        $employerEmail = $organization->employer->email;
        $employeeEmails = $organization->employees()->pluck('email')->all();

        $summary = app(DemoOrganizationCleanupService::class)->deleteOrganization($organization);

        $this->assertSame(1, $summary->organizations);
        $this->assertGreaterThan(0, $summary->totalRecords());
        $this->assertDatabaseMissing('organizations', ['id' => $organizationId]);
        $this->assertDatabaseMissing('users', ['email' => $employerEmail]);
        foreach ($employeeEmails as $email) {
            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
        $this->assertSame(0, Call::query()->where('organization_id', $organizationId)->count());
        $this->assertSame(0, ConversationAnalysis::query()->where('organization_id', $organizationId)->count());
        $this->assertSame(0, Customer::query()->where('organization_id', $organizationId)->count());
        $this->assertSame(DemoCatalog::ORGANIZATION_COUNT - 1, Organization::query()->demo()->count());
    }

    public function test_deletes_all_demo_organizations_without_touching_production_data(): void
    {
        $this->seedDemoData();

        $productionEmployer = User::factory()->employer()->create([
            'email' => 'real-employer@example.com',
        ]);
        $productionOrganization = Organization::factory()->create([
            'user_id' => $productionEmployer->id,
            'title' => 'Real Customer Org',
            'is_demo' => false,
        ]);

        $summary = app(DemoOrganizationCleanupService::class)->deleteAll();

        $this->assertSame(DemoCatalog::ORGANIZATION_COUNT, $summary->organizations);
        $this->assertSame(0, Organization::query()->demo()->count());
        $this->assertDatabaseHas('organizations', ['id' => $productionOrganization->id, 'is_demo' => false]);
        $this->assertDatabaseHas('users', ['email' => 'real-employer@example.com']);
    }

    public function test_refuses_to_delete_non_demo_organization(): void
    {
        $employer = User::factory()->employer()->create([
            'email' => 'real-employer@example.com',
        ]);
        $organization = Organization::factory()->create([
            'user_id' => $employer->id,
            'is_demo' => false,
        ]);

        $this->expectException(DemoCleanupException::class);

        app(DemoOrganizationCleanupService::class)->deleteOrganization($organization);
    }

    public function test_refuses_organization_marked_demo_with_non_demo_employer_email(): void
    {
        $employer = User::factory()->employer()->create([
            'email' => 'real-employer@example.com',
        ]);
        $organization = Organization::factory()->create([
            'user_id' => $employer->id,
            'is_demo' => true,
        ]);

        $this->expectException(DemoCleanupException::class);

        app(DemoOrganizationCleanupService::class)->deleteOrganization($organization);
    }

    public function test_demo_seeder_marks_organizations_as_demo(): void
    {
        $this->seedDemoData();

        $this->assertSame(
            DemoCatalog::ORGANIZATION_COUNT,
            Organization::query()->where('is_demo', true)->count(),
        );
    }

    private function seedDemoData(): void
    {
        $this->seed(PlatformFoundationSeeder::class);
        $this->seed(DemoSeeder::class);
    }
}
