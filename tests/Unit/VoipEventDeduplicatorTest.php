<?php

namespace Tests\Unit;

use App\Application\Voip\Services\VoipEventDeduplicator;
use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Models\VoipCallLog;
use PHPUnit\Framework\TestCase;

class VoipEventDeduplicatorTest extends TestCase
{
    public function test_cdr_feed_never_overwrites_an_existing_call(): void
    {
        $existing = new VoipCallLog(['status' => CallStatus::Ringing]);

        $event = new NormalizedWebhookEvent(
            type: VoipWebhookEventType::CallEnded,
            callId: '1759650000.100',
            status: CallStatus::Completed,
            recordingUrl: 'http://pbx/rec.wav',
            rawPayload: ['source' => 'cdr'],
        );

        $this->assertTrue((new VoipEventDeduplicator)->isDuplicate($event, $existing));
    }

    public function test_cdr_feed_creates_calls_no_other_source_sent(): void
    {
        $event = new NormalizedWebhookEvent(
            type: VoipWebhookEventType::CallMissed,
            callId: '1759650000.101',
            rawPayload: ['source' => 'cdr'],
        );

        $this->assertFalse((new VoipEventDeduplicator)->isDuplicate($event, null));
    }

    public function test_other_sources_still_update_existing_calls(): void
    {
        $existing = new VoipCallLog(['status' => CallStatus::Ringing]);

        $event = new NormalizedWebhookEvent(
            type: VoipWebhookEventType::CallEnded,
            callId: '1759650000.102',
            status: CallStatus::Completed,
            recordingUrl: 'http://pbx/rec.wav',
        );

        $this->assertFalse((new VoipEventDeduplicator)->isDuplicate($event, $existing));
    }
}
