<?php

namespace Tests\Unit;

use App\Application\Voip\Services\VoipWebhookDispatcher;
use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use Tests\TestCase;

class VoipWebhookDispatcherTest extends TestCase
{
    public function test_started_at_with_an_offset_is_stored_in_app_time(): void
    {
        $data = $this->callLogData('2026-10-05T08:25:01+03:30');

        $this->assertSame('2026-10-05 04:55:01', $data['started_at']);
    }

    public function test_started_at_in_utc_zulu_is_kept(): void
    {
        $data = $this->callLogData('2026-10-05T04:55:01Z');

        $this->assertSame('2026-10-05 04:55:01', $data['started_at']);
    }

    public function test_started_at_without_an_offset_is_unchanged(): void
    {
        $data = $this->callLogData('2026-10-05 08:25:01');

        $this->assertSame('2026-10-05 08:25:01', $data['started_at']);
    }

    /** @return array<string, mixed> */
    private function callLogData(string $startedAt): array
    {
        return app(VoipWebhookDispatcher::class)->toCallLogData(1, 1, 'custom', new NormalizedWebhookEvent(
            type: VoipWebhookEventType::CallEnded,
            callId: '1791176101.41261',
            startedAt: $startedAt,
            duration: 31,
        ))->toArray();
    }
}
