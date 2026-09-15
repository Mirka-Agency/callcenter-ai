<?php

namespace Tests\Unit;

use App\Application\Voip\Support\VoipReportFilter;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Infrastructure\Voip\Adapters\NullVoipAdapter;
use App\Models\Organization;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use App\Models\VoipWebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoipReportFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_logs_can_be_filtered_by_call_id_extension_and_direction(): void
    {
        $connection = $this->createConnection();

        $inbound = $this->createCallLog($connection, [
            'external_call_id' => 'call-in-101',
            'direction' => CallDirection::Inbound,
            'source_number' => '09120000000',
            'destination_number' => '101',
            'raw_payload' => ['resolved_extension' => '101', 'cuid' => 'call-in-101'],
        ]);
        $outbound = $this->createCallLog($connection, [
            'external_call_id' => 'call-out-202',
            'direction' => CallDirection::Outbound,
            'source_number' => '202',
            'destination_number' => '09121111111',
            'raw_payload' => ['extension' => '202'],
        ]);
        $this->createCallLog($connection, [
            'external_call_id' => 'call-other',
            'direction' => CallDirection::Inbound,
            'source_number' => '09330000000',
            'destination_number' => '303',
            'raw_payload' => ['resolved_extension' => '303'],
        ]);

        $this->assertEqualsCanonicalizing(
            [$inbound->id],
            VoipReportFilter::applyCallLogFilters($connection->callLogs()->getQuery(), [
                'call_id' => 'in-101',
            ])->pluck('id')->all(),
        );

        $this->assertEqualsCanonicalizing(
            [$outbound->id],
            VoipReportFilter::applyCallLogFilters($connection->callLogs()->getQuery(), [
                'extension' => '202',
                'direction' => CallDirection::Outbound->value,
            ])->pluck('id')->all(),
        );

        $this->assertEqualsCanonicalizing(
            [$inbound->id],
            VoipReportFilter::applyCallLogFilters($connection->callLogs()->getQuery(), [
                'extension' => '101',
                'direction' => CallDirection::Inbound->value,
            ])->pluck('id')->all(),
        );
    }

    public function test_webhook_logs_can_be_filtered_by_call_id_extension_and_payload_direction(): void
    {
        $connection = $this->createConnection();

        $inbound = $this->createWebhookLog($connection, [
            'resolved_extension' => '101',
            'payload' => [
                'cuid' => 'simotel-in-1',
                'type' => 'incoming',
                'exten' => '101',
            ],
        ]);
        $outbound = $this->createWebhookLog($connection, [
            'resolved_extension' => '202',
            'payload' => [
                'call_id' => 'custom-out-2',
                'direction' => 'outbound',
                'extension' => '202',
            ],
        ]);
        $this->createWebhookLog($connection, [
            'resolved_extension' => '303',
            'payload' => [
                'unique_id' => 'other-3',
                'direction' => 'in',
                'extension' => '303',
            ],
        ]);

        $this->assertEqualsCanonicalizing(
            [$inbound->id],
            VoipReportFilter::applyWebhookLogFilters($connection->webhookLogs()->getQuery(), [
                'call_id' => 'simotel-in-1',
            ])->pluck('id')->all(),
        );

        $this->assertEqualsCanonicalizing(
            [$outbound->id],
            VoipReportFilter::applyWebhookLogFilters($connection->webhookLogs()->getQuery(), [
                'extension' => '202',
                'direction' => CallDirection::Outbound->value,
            ])->pluck('id')->all(),
        );

        $this->assertEqualsCanonicalizing(
            [$inbound->id],
            VoipReportFilter::applyWebhookLogFilters($connection->webhookLogs()->getQuery(), [
                'direction' => CallDirection::Inbound->value,
                'extension' => '101',
            ])->pluck('id')->all(),
        );
    }

    public function test_webhook_direction_filter_matches_related_call_log_when_payload_has_no_direction(): void
    {
        $connection = $this->createConnection();

        $this->createCallLog($connection, [
            'external_call_id' => 'linked-call',
            'direction' => CallDirection::Outbound,
            'source_number' => '202',
            'destination_number' => '09120000000',
            'raw_payload' => [],
        ]);

        $linked = $this->createWebhookLog($connection, [
            'resolved_extension' => '202',
            'payload' => [
                'cuid' => 'linked-call',
                'event' => 'cdr',
            ],
        ]);
        $this->createWebhookLog($connection, [
            'resolved_extension' => '202',
            'payload' => [
                'cuid' => 'unrelated',
                'event' => 'cdr',
            ],
        ]);

        $this->assertEqualsCanonicalizing(
            [$linked->id],
            VoipReportFilter::applyWebhookLogFilters($connection->webhookLogs()->getQuery(), [
                'direction' => CallDirection::Outbound->value,
            ])->pluck('id')->all(),
        );
    }

    public function test_blank_filters_do_not_narrow_results(): void
    {
        $connection = $this->createConnection();
        $this->createCallLog($connection, [
            'external_call_id' => 'keep-me',
            'direction' => CallDirection::Inbound,
            'source_number' => '09120000000',
            'destination_number' => '101',
        ]);

        $this->assertSame(1, VoipReportFilter::applyCallLogFilters($connection->callLogs()->getQuery(), [
            'call_id' => '  ',
            'extension' => null,
            'direction' => '',
        ])->count());
    }

    public function test_extension_and_direction_are_extracted_for_display(): void
    {
        $connection = $this->createConnection();
        $log = $this->createCallLog($connection, [
            'external_call_id' => 'display-1',
            'direction' => CallDirection::Inbound,
            'source_number' => '09120000000',
            'destination_number' => '02100000000',
            'raw_payload' => ['resolved_extension' => '553'],
        ]);

        $this->assertSame('553', VoipReportFilter::extensionFromCallLog($log));
        $this->assertSame('simotel-9', VoipReportFilter::callIdFromPayload(['cuid' => 'simotel-9']));
        $this->assertSame(CallDirection::Inbound, VoipReportFilter::directionFromPayload(['type' => 'incoming']));
        $this->assertSame(CallDirection::Outbound, VoipReportFilter::directionFromPayload(['direction' => 'out']));
    }

    private function createConnection(): OrganizationVoipConnection
    {
        $organization = Organization::factory()->create();
        $provider = VoipProvider::query()->create([
            'name' => 'Test',
            'code' => VoipProviderCode::Novatel->value,
            'adapter_class' => NullVoipAdapter::class,
            'is_active' => true,
        ]);

        return OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $provider->id,
            'name' => 'Primary',
            'credentials' => ['api_url' => 'https://example.com', 'api_key' => 'x'],
            'is_active' => true,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function createCallLog(OrganizationVoipConnection $connection, array $attributes): VoipCallLog
    {
        return VoipCallLog::query()->create([
            'organization_id' => $connection->organization_id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Novatel->value,
            'status' => CallStatus::Completed,
            'started_at' => now(),
            ...$attributes,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function createWebhookLog(OrganizationVoipConnection $connection, array $attributes): VoipWebhookLog
    {
        return VoipWebhookLog::query()->create([
            'organization_voip_connection_id' => $connection->id,
            'event_type' => VoipWebhookEventType::CallEnded->value,
            'status' => VoipLogStatus::Success,
            'message' => 'ok',
            ...$attributes,
        ]);
    }
}
