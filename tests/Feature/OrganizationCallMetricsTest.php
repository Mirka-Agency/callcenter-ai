<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Models\Call;
use App\Models\Organization;
use App\Models\User;
use App\Services\EmployerDashboardAnalytics;
use App\Services\Reports\OrganizationCallMetrics;
use Database\Seeders\PlatformFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationCallMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_todays_calls_from_call_records_not_only_voip_logs(): void
    {
        $this->seed(PlatformFoundationSeeder::class);

        $employer = User::factory()->employer()->create();
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        Call::query()->create([
            'organization_id' => $organization->id,
            'external_call_id' => 'metrics-test-today',
            'provider_code' => 'demo',
            'source' => ConversationSource::Imported,
            'direction' => 'inbound',
            'caller_number' => '09121234567',
            'receiver_number' => '02112345678',
            'status' => 'completed',
            'processing_status' => CallProcessingStatus::Analyzed,
            'started_at' => now()->startOfDay()->addHours(10),
            'ended_at' => now()->startOfDay()->addHours(10)->addMinutes(5),
            'duration_seconds' => 300,
        ]);

        $metrics = app(OrganizationCallMetrics::class);

        $this->assertSame(1, $metrics->countToday($organization->id));
        $this->assertSame(1, EmployerDashboardAnalytics::forOrganization($organization->id)->cockpit()['calls_today']);
    }

    public function test_ignores_calls_outside_today_window(): void
    {
        $this->seed(PlatformFoundationSeeder::class);

        $employer = User::factory()->employer()->create();
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        Call::query()->create([
            'organization_id' => $organization->id,
            'external_call_id' => 'metrics-test-yesterday',
            'provider_code' => 'demo',
            'source' => ConversationSource::Imported,
            'direction' => 'inbound',
            'caller_number' => '09121234567',
            'receiver_number' => '02112345678',
            'status' => 'completed',
            'processing_status' => CallProcessingStatus::Analyzed,
            'started_at' => now()->subDay()->setTime(15, 30),
            'ended_at' => now()->subDay()->setTime(15, 35),
            'duration_seconds' => 300,
        ]);

        $this->assertSame(0, app(OrganizationCallMetrics::class)->countToday($organization->id));
    }

    public function test_counts_todays_calls_when_voip_webhook_omitted_started_at(): void
    {
        $this->seed(PlatformFoundationSeeder::class);

        $employer = User::factory()->employer()->create();
        $organization = Organization::factory()->create(['user_id' => $employer->id]);

        Call::query()->create([
            'organization_id' => $organization->id,
            'external_call_id' => 'metrics-test-null-start',
            'provider_code' => 'custom',
            'source' => ConversationSource::Voip,
            'direction' => 'inbound',
            'caller_number' => '09121234567',
            'receiver_number' => '41909000',
            'status' => 'completed',
            'processing_status' => CallProcessingStatus::Pending,
            'started_at' => null,
            'duration_seconds' => 180,
        ]);

        $this->assertSame(1, app(OrganizationCallMetrics::class)->countToday($organization->id));
        $this->assertSame(
            1,
            EmployerDashboardAnalytics::forOrganization($organization->id)->cockpit()['calls_today'],
        );
    }
}
