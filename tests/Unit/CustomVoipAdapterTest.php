<?php

namespace Tests\Unit;

use App\Domain\Voip\DTOs\VoipConnectionConfig;
use App\Domain\Voip\DTOs\VoipCredentials;
use App\Domain\Voip\DTOs\VoipSettings;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Infrastructure\Voip\Adapters\CustomVoipAdapter;
use App\Infrastructure\Voip\Support\WebhookPayloadNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomVoipAdapterTest extends TestCase
{
    public function test_normalize_standard_custom_payload(): void
    {
        $adapter = $this->adapter();

        $event = $adapter->normalizeWebhook([
            'event' => 'call.ended',
            'call_id' => 'call-123',
            'direction' => 'inbound',
            'from' => '09121234567',
            'to' => '101',
            'status' => 'completed',
            'recording_url' => 'https://voip.example.com/rec.mp3',
            'duration' => 120,
        ]);

        $this->assertSame(VoipWebhookEventType::CallEnded, $event->type);
        $this->assertSame('call-123', $event->callId);
        $this->assertSame(CallDirection::Inbound, $event->direction);
        $this->assertSame('09121234567', $event->sourceNumber);
        $this->assertSame('101', $event->destinationNumber);
        $this->assertSame(CallStatus::Completed, $event->status);
        $this->assertSame('https://voip.example.com/rec.mp3', $event->recordingUrl);
        $this->assertSame(120, $event->duration);
    }

    public function test_normalize_supports_common_field_aliases(): void
    {
        $adapter = $this->adapter();

        $event = $adapter->normalizeWebhook([
            'type' => 'call.ended',
            'unique_id' => 'abc',
            'caller_number' => '02111111111',
            'receiver_number' => '200',
            'audio_url' => 'https://cdn.example.com/a.mp3',
            'call_type' => 'outgoing',
        ]);

        $this->assertSame('abc', $event->callId);
        $this->assertSame('02111111111', $event->sourceNumber);
        $this->assertSame('200', $event->destinationNumber);
        $this->assertSame('https://cdn.example.com/a.mp3', $event->recordingUrl);
        $this->assertSame(CallDirection::Outbound, $event->direction);
    }

    public function test_normalize_uses_configurable_field_mapping(): void
    {
        $adapter = new CustomVoipAdapter;
        $adapter->configure(new VoipConnectionConfig(
            connectionId: 1,
            organizationId: 1,
            providerCode: VoipProviderCode::Custom,
            name: 'Custom',
            credentials: new VoipCredentials(apiUrl: ''),
            settings: new VoipSettings(webhookFieldMapping: [
                'call_id' => 'data.id',
                'from' => 'data.caller.phone',
                'to' => 'data.agent.extension',
                'recording_url' => 'data.audio.link',
                'event' => 'data.event_name',
            ]),
        ));

        $event = $adapter->normalizeWebhook([
            'data' => [
                'id' => 'nested-1',
                'event_name' => 'call.ended',
                'caller' => ['phone' => '09120001111'],
                'agent' => ['extension' => '500'],
                'audio' => ['link' => 'https://files.example.com/1.mp3'],
            ],
        ]);

        $this->assertSame('nested-1', $event->callId);
        $this->assertSame('09120001111', $event->sourceNumber);
        $this->assertSame('500', $event->destinationNumber);
        $this->assertSame('https://files.example.com/1.mp3', $event->recordingUrl);
    }

    #[DataProvider('normalizerAliasProvider')]
    public function test_normalizer_maps_aliases(array $payload, string $field, mixed $expected): void
    {
        $event = (new WebhookPayloadNormalizer)->normalize($payload, provider: 'custom');

        $actual = match ($field) {
            'call_id' => $event->callId,
            'from' => $event->sourceNumber,
            'recording_url' => $event->recordingUrl,
            default => null,
        };

        $this->assertSame($expected, $actual);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string, 2: mixed}> */
    public static function normalizerAliasProvider(): array
    {
        return [
            'phone alias' => [['phone' => '09123334444', 'call_id' => '1'], 'from', '09123334444'],
            'audio_link alias' => [['audio_link' => 'https://x.test/a.mp3', 'call_id' => '1'], 'recording_url', 'https://x.test/a.mp3'],
        ];
    }

    private function adapter(): CustomVoipAdapter
    {
        $adapter = new CustomVoipAdapter;
        $adapter->configure(new VoipConnectionConfig(
            connectionId: 1,
            organizationId: 1,
            providerCode: VoipProviderCode::Custom,
            name: 'Custom',
            credentials: new VoipCredentials(apiUrl: ''),
            settings: new VoipSettings,
        ));

        return $adapter;
    }
}
