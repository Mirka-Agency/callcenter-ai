<?php

namespace Tests\Unit;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Domain\Voip\DTOs\VoipConnectionConfig;
use App\Domain\Voip\DTOs\VoipCredentials;
use App\Domain\Voip\DTOs\VoipSettings;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Enums\UserRole;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Infrastructure\Voip\Services\SimotelAgentExtensionCache;
use App\Models\EmployeeIntegrationMeta;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\User;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SimotelVoipAdapterTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('cdrDispositionProvider')]
    public function test_normalize_cdr_maps_disposition_and_direction(
        array $payload,
        VoipWebhookEventType $expectedType,
        CallStatus $expectedStatus,
        ?CallDirection $expectedDirection,
        ?string $expectedRecordingUrl,
    ): void {
        $adapter = $this->adapter();

        $event = $adapter->normalizeWebhook($payload);

        $this->assertSame($expectedType, $event->type);
        $this->assertSame($expectedStatus, $event->status);
        $this->assertSame($expectedDirection, $event->direction);
        $this->assertSame($expectedRecordingUrl, $event->recordingUrl);
        $this->assertSame('1610778618.378', $event->callId);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: VoipWebhookEventType, 2: CallStatus, 3: ?CallDirection, 4: ?string}> */
    public static function cdrDispositionProvider(): array
    {
        return [
            'answered_incoming_with_record' => [
                [
                    'event_name' => 'CDR',
                    'starttime' => '2021-01-16 06:30:37.471398',
                    'endtime' => '2021-01-16 06:30:46.471398',
                    'src' => '09120000000',
                    'dst' => '553',
                    'type' => 'incoming',
                    'disposition' => 'ANSWERED',
                    'billsec' => 9,
                    'record' => '20210116_1610778618.378.mp3',
                    'unique_id' => '1610778618.378',
                ],
                VoipWebhookEventType::CallEnded,
                CallStatus::Completed,
                CallDirection::Inbound,
                'simotel://20210116_1610778618.378.mp3',
            ],
            'no_answer_local' => [
                [
                    'event_name' => 'Cdr',
                    'starttime' => '2021-01-16 07:17:00.508368',
                    'endtime' => '2021-01-16 07:17:01.508368',
                    'src' => '992',
                    'dst' => '66',
                    'type' => 'local',
                    'disposition' => 'NO ANSWER',
                    'duration' => 1,
                    'unique_id' => '1610778618.378',
                ],
                VoipWebhookEventType::CallMissed,
                CallStatus::Missed,
                CallDirection::Inbound,
                null,
            ],
            'busy_outgoing' => [
                [
                    'event_name' => 'cdr',
                    'src' => '100',
                    'dst' => '09121111111',
                    'type' => 'outgoing',
                    'disposition' => 'Busy',
                    'unique_id' => '1610778618.378',
                ],
                VoipWebhookEventType::CallMissed,
                CallStatus::Busy,
                CallDirection::Outbound,
                null,
            ],
        ];
    }

    public function test_unknown_event_returns_unknown_type(): void
    {
        $event = $this->adapter()->normalizeWebhook(['event_name' => 'SomethingElse', 'unique_id' => '1']);

        $this->assertSame(VoipWebhookEventType::Unknown, $event->type);
    }

    public function test_new_state_in_use_caches_exten_and_cdr_reads_it(): void
    {
        $adapter = $this->adapter(connectionId: 7);

        $stateEvent = $adapter->normalizeWebhook([
            'event_name' => 'New State',
            'exten' => '991',
            'state' => 'InUse',
            'participant' => '09198202502',
            'direction' => 'in',
            'unique_id' => '1784375548.939408',
            'cuid' => '1784375548.939408',
        ]);

        $this->assertSame(VoipWebhookEventType::AgentStateChanged, $stateEvent->type);
        $this->assertSame('991', $stateEvent->extension);
        $this->assertSame('991', app(SimotelAgentExtensionCache::class)->get(7, '1784375548.939408'));

        $cdrEvent = $adapter->normalizeWebhook([
            'event_name' => 'Cdr',
            'src' => '09198202502',
            'dst' => '982191093492',
            'type' => 'incoming',
            'disposition' => 'ANSWERED',
            'duration' => 213,
            'billsec' => 106,
            'unique_id' => '1784375548.939408',
            'cuid' => '1784375548.939408',
            'did' => '982191093492',
        ]);

        $this->assertSame('982191093492', $cdrEvent->destinationNumber);
        $this->assertSame('991', $cdrEvent->extension);
    }

    public function test_cdr_with_did_enriches_extension_from_quick_info(): void
    {
        Http::fake([
            'http://simotel.test/API/v4/reports/quick/info' => Http::response([
                'success' => 1,
                'data' => [
                    [
                        'cuid' => '1784375548.939408',
                        'src' => '09198202502',
                        'dst' => '564',
                        'disposition' => 'ANSWERED',
                    ],
                ],
            ], 200),
        ]);

        $event = $this->adapter()->normalizeWebhook([
            'event_name' => 'Cdr',
            'src' => '09198202502',
            'dst' => '982191093492',
            'type' => 'incoming',
            'disposition' => 'ANSWERED',
            'unique_id' => '1784375548.939408',
            'cuid' => '1784375548.939408',
            'did' => '982191093492',
        ]);

        $this->assertSame('564', $event->extension);
        $this->assertSame('982191093492', $event->destinationNumber);

        Http::assertSent(fn ($request) => $request->url() === 'http://simotel.test/API/v4/reports/quick/info'
            && $request['cuid'] === '1784375548.939408');
    }

    public function test_cdr_falls_back_to_quick_search_when_quick_info_empty(): void
    {
        Http::fake([
            'http://simotel.test/API/v4/reports/quick/info' => Http::response([
                'success' => 1,
                'data' => [],
            ], 200),
            'http://simotel.test/API/v4/reports/quick/search' => Http::response([
                'success' => 1,
                'data' => [
                    'data' => [
                        [
                            'cuid' => '1784375548.939408',
                            'src' => '09198202502',
                            'dst' => '777',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $event = $this->adapter()->normalizeWebhook([
            'event_name' => 'Cdr',
            'src' => '09198202502',
            'dst' => '982191093492',
            'type' => 'incoming',
            'disposition' => 'ANSWERED',
            'cuid' => '1784375548.939408',
            'did' => '982191093492',
        ]);

        $this->assertSame('777', $event->extension);
    }

    public function test_extension_mapping_maps_did_to_agent_extension(): void
    {
        $adapter = $this->adapter(settings: new VoipSettings(
            extensionMapping: ['982191093492' => '101'],
        ));

        Http::fake();

        $event = $adapter->normalizeWebhook([
            'event_name' => 'Cdr',
            'src' => '09198202502',
            'dst' => '982191093492',
            'type' => 'incoming',
            'disposition' => 'ANSWERED',
            'cuid' => 'no-api-needed.1',
            'did' => '982191093492',
        ]);

        $this->assertSame('101', $event->extension);
    }

    public function test_call_employee_resolver_uses_resolved_extension_and_mapping(): void
    {
        $organization = Organization::factory()->create();
        $provider = VoipProvider::query()->create([
            'name' => 'Simotel',
            'code' => VoipProviderCode::Simotel->value,
            'adapter_class' => SimotelVoipAdapter::class,
            'is_active' => true,
        ]);
        $connection = OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $provider->id,
            'name' => 'Simotel',
            'credentials' => [],
            'settings' => [
                'extension_mapping' => ['982191093492' => '101'],
            ],
            'is_active' => true,
        ]);
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'Ali',
            'last_name' => 'Agent',
            'is_active' => true,
        ]);
        EmployeeIntegrationMeta::query()->create([
            'organization_user_id' => $employee->id,
            'integratable_type' => OrganizationVoipConnection::class,
            'integratable_id' => $connection->id,
            'key' => 'extension',
            'value' => '101',
        ]);

        $log = VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Simotel->value,
            'external_call_id' => 'c1',
            'direction' => 'inbound',
            'source_number' => '09198202502',
            'destination_number' => '982191093492',
            'status' => 'completed',
            'raw_payload' => [
                'did' => '982191093492',
            ],
        ]);

        $resolved = app(CallEmployeeResolver::class)->resolveFromCallLog($log);

        $this->assertSame($employee->id, $resolved);
    }

    private function adapter(
        int $connectionId = 1,
        ?VoipSettings $settings = null,
    ): SimotelVoipAdapter {
        $adapter = new SimotelVoipAdapter;
        $adapter->configure(new VoipConnectionConfig(
            connectionId: $connectionId,
            organizationId: 1,
            providerCode: VoipProviderCode::Simotel,
            name: 'Simotel',
            credentials: new VoipCredentials(apiUrl: 'http://simotel.test/API/v4', apiKey: 'key'),
            settings: $settings ?? new VoipSettings,
        ));

        return $adapter;
    }
}
