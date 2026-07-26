<?php

namespace App\Application\Voip\Jobs;

use App\Application\Voip\Services\VoipConnectionResolver;
use App\Application\Voip\Services\VoipEventIngestionService;
use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipEventSource;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Models\VoipCallLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * After IncomingCall, Simotel may never send a CDR (common for unanswered).
 * Resolve final disposition via Quick Search by cuid.
 *
 * @see https://simotel.com/wiki/fa/developers/simotelapi/v4/report/quick_search/
 */
class ResolveSimotelCallOutcomeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $organizationId,
        public int $connectionId,
        public string $callId,
    ) {
        $this->onQueue((string) config('voip.queue', 'default'));
    }

    public function handle(
        VoipConnectionResolver $resolver,
        VoipEventIngestionService $ingestion,
    ): void {
        $callLog = VoipCallLog::query()
            ->where('organization_voip_connection_id', $this->connectionId)
            ->where('external_call_id', $this->callId)
            ->first();

        if (! $callLog || $this->isTerminalStatus($callLog->status)) {
            return;
        }

        [$config, $adapter] = $resolver->resolve($this->organizationId, $this->connectionId);

        if (! $adapter instanceof SimotelVoipAdapter) {
            return;
        }

        $details = $adapter->getCallDetails($this->callId);
        $rows = is_array($details->data['rows'] ?? null) ? $details->data['rows'] : [];

        if ($details->success && $rows !== []) {
            /** @var array<string, mixed> $row */
            $row = $rows[0];
            $event = $adapter->normalizeOutcomeFromCallRow($this->callId, $row)
                ->withSource(VoipEventSource::Polling, VoipProviderCode::Simotel->value);

            $ingestion->ingest($config, $event, $row, forceReplay: true);

            Log::info('simotel_call_outcome_resolved', [
                'connection_id' => $this->connectionId,
                'call_id' => $this->callId,
                'event_type' => $event->type->value,
                'status' => $event->status?->value,
            ]);

            return;
        }

        // No CDR / Quick Search row after the grace period → missed.
        $event = new NormalizedWebhookEvent(
            type: VoipWebhookEventType::CallMissed,
            callId: $this->callId,
            direction: $callLog->direction ?? CallDirection::Inbound,
            sourceNumber: $callLog->source_number,
            destinationNumber: $callLog->destination_number,
            status: CallStatus::Missed,
            startedAt: $callLog->started_at?->toDateTimeString(),
            endedAt: now()->toDateTimeString(),
            rawPayload: [
                'resolved_by' => 'simotel_outcome_timeout',
                'quick_search' => $details->data,
            ],
            source: VoipEventSource::Polling,
            provider: VoipProviderCode::Simotel->value,
        );

        $ingestion->ingest($config, $event, $event->rawPayload, forceReplay: true);

        Log::info('simotel_call_outcome_marked_missed', [
            'connection_id' => $this->connectionId,
            'call_id' => $this->callId,
            'reason' => $details->success ? 'empty_rows' : 'api_failed',
        ]);
    }

    private function isTerminalStatus(?CallStatus $status): bool
    {
        return in_array($status, [
            CallStatus::Completed,
            CallStatus::Missed,
            CallStatus::Busy,
            CallStatus::Failed,
            CallStatus::Cancelled,
            CallStatus::Answered,
        ], true);
    }
}
