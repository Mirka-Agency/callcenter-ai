<?php

namespace Tests\Unit;

use App\Domain\Voip\DTOs\VoipConnectionConfig;
use App\Domain\Voip\DTOs\VoipCredentials;
use App\Domain\Voip\DTOs\VoipSettings;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SimotelVoipAdapterTest extends TestCase
{
    #[DataProvider('cdrDispositionProvider')]
    public function test_normalize_cdr_maps_disposition_and_direction(
        array $payload,
        VoipWebhookEventType $expectedType,
        CallStatus $expectedStatus,
        ?CallDirection $expectedDirection,
        ?string $expectedRecordingUrl,
    ): void {
        $adapter = new SimotelVoipAdapter;
        $adapter->configure(new VoipConnectionConfig(
            connectionId: 1,
            organizationId: 1,
            providerCode: VoipProviderCode::Simotel,
            name: 'Simotel',
            credentials: new VoipCredentials(apiUrl: 'http://simotel.test/API/v4'),
            settings: new VoipSettings,
        ));

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
        $adapter = new SimotelVoipAdapter;
        $adapter->configure(new VoipConnectionConfig(
            connectionId: 1,
            organizationId: 1,
            providerCode: VoipProviderCode::Simotel,
            name: 'Simotel',
            credentials: new VoipCredentials(apiUrl: 'http://simotel.test/API/v4'),
            settings: new VoipSettings,
        ));

        $event = $adapter->normalizeWebhook(['event_name' => 'NewState', 'unique_id' => '1']);

        $this->assertSame(VoipWebhookEventType::Unknown, $event->type);
    }
}
