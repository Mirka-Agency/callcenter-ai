<?php

namespace App\Application\Voip\Jobs;

use App\Application\Voip\Services\VoipConnectionResolver;
use App\Application\Voip\Services\VoipEventIngestionService;
use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\DTOs\VoipConnectionConfig;
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
 * After IncomingCall, wait then resolve disposition via Quick Search.
 * If API returns Access denied, leave ringing and wait for CDR webhook — never false-miss.
 *
 * @see https://simotel.com/wiki/fa/developers/simotelapi/v4/report/quick_search/
 */
class ResolveSimotelCallOutcomeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public int $organizationId,
        public int $connectionId,
        public string $callId,
        public bool $force = false,
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

        if (! $callLog) {
            return;
        }

        if ($this->isTerminalStatus($callLog->status) && ! $this->shouldForceRecheck($callLog)) {
            return;
        }

        [$config, $adapter] = $resolver->resolve($this->organizationId, $this->connectionId);

        if (! $adapter instanceof SimotelVoipAdapter) {
            return;
        }

        $details = $adapter->getCallDetailsWithContext($this->callId, [
            'started_at' => $callLog->started_at,
            'source_number' => $callLog->source_number,
        ]);

        // API key lacks reports/quick permission — do NOT mark missed.
        // Final disposition must come from the CDR webhook (or after permissions are fixed).
        if ($adapter->isAccessDeniedResult($details)) {
            $payload = is_array($callLog->raw_payload) ? $callLog->raw_payload : [];
            $callLog->update([
                'raw_payload' => array_merge($payload, [
                    'outcome_error' => 'simotel_api_access_denied',
                    'outcome_message' => $details->error,
                    'outcome_checked_at' => now()->toIso8601String(),
                ]),
            ]);

            Log::warning('simotel_call_outcome_access_denied', [
                'connection_id' => $this->connectionId,
                'call_id' => $this->callId,
                'message' => $details->error,
            ]);

            return;
        }

        $rows = is_array($details->data['rows'] ?? null) ? $details->data['rows'] : [];

        if ($details->success && $rows !== []) {
            /** @var array<string, mixed> $row */
            $row = $rows[0];
            $event = $adapter->normalizeOutcomeFromCallRow($this->callId, $row)
                ->withSource(VoipEventSource::Polling, VoipProviderCode::Simotel->value);

            if ($event->type === VoipWebhookEventType::CallStarted
                || $event->status === CallStatus::Ringing) {
                $this->retryOrMiss($ingestion, $config, $callLog, 'incomplete_row');

                return;
            }

            $ingestion->ingest($config, $event, $row, forceReplay: true);

            Log::info('simotel_call_outcome_resolved', [
                'connection_id' => $this->connectionId,
                'call_id' => $this->callId,
                'event_type' => $event->type->value,
                'status' => $event->status?->value,
                'attempt' => $this->attempts(),
            ]);

            return;
        }

        $this->retryOrMiss(
            $ingestion,
            $config,
            $callLog,
            $details->success ? 'empty_rows' : 'api_failed',
            $details->data,
        );
    }

    private function shouldForceRecheck(VoipCallLog $callLog): bool
    {
        if (! $this->force) {
            return false;
        }

        if ($callLog->status !== CallStatus::Missed) {
            return false;
        }

        $resolvedBy = data_get($callLog->raw_payload, 'resolved_by');

        return $resolvedBy === 'simotel_outcome_timeout' || $resolvedBy === null;
    }

    private function retryOrMiss(
        VoipEventIngestionService $ingestion,
        VoipConnectionConfig $config,
        VoipCallLog $callLog,
        string $reason,
        mixed $apiData = null,
    ): void {
        $retrySeconds = max(30, (int) config('voip.simotel_outcome_retry_seconds', 60));

        if ($this->attempts() < $this->tries) {
            Log::info('simotel_call_outcome_retry', [
                'connection_id' => $this->connectionId,
                'call_id' => $this->callId,
                'reason' => $reason,
                'attempt' => $this->attempts(),
                'retry_in' => $retrySeconds,
            ]);

            $this->release($retrySeconds);

            return;
        }

        if ($this->force && $callLog->status === CallStatus::Missed) {
            Log::info('simotel_call_outcome_recheck_unchanged', [
                'connection_id' => $this->connectionId,
                'call_id' => $this->callId,
                'reason' => $reason,
            ]);

            return;
        }

        // Still no API row and no CDR webhook after retries → missed.
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
                'reason' => $reason,
                'quick_search' => $apiData,
            ],
            source: VoipEventSource::Polling,
            provider: VoipProviderCode::Simotel->value,
        );

        $ingestion->ingest($config, $event, $event->rawPayload, forceReplay: true);

        Log::info('simotel_call_outcome_marked_missed', [
            'connection_id' => $this->connectionId,
            'call_id' => $this->callId,
            'reason' => $reason,
            'attempts' => $this->attempts(),
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
