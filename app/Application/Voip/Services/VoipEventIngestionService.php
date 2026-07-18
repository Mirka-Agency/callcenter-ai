<?php

namespace App\Application\Voip\Services;

use App\Domain\Voip\Contracts\VoipCallLogRepositoryInterface;
use App\Domain\Voip\Contracts\VoipLogRepositoryInterface;
use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use App\Domain\Voip\DTOs\VoipConnectionConfig;
use App\Domain\Voip\Enums\VoipEventSource;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipOperation;
use App\Domain\Voip\ValueObjects\VoipOperationResult;
use App\Models\VoipCallLog;
use Illuminate\Support\Facades\Log;

class VoipEventIngestionService
{
    public function __construct(
        private VoipWebhookDispatcher $dispatcher,
        private VoipCallLogRepositoryInterface $callLogs,
        private VoipLogRepositoryInterface $logs,
        private VoipEventDeduplicator $deduplicator,
    ) {}

    public function ingest(
        VoipConnectionConfig $config,
        NormalizedWebhookEvent $event,
        ?array $rawPayload = null,
        bool $forceReplay = false,
    ): VoipOperationResult {
        $existing = $event->callId
            ? VoipCallLog::query()
                ->where('organization_voip_connection_id', $config->connectionId)
                ->where('external_call_id', $event->callId)
                ->first()
            : null;

        if (! $forceReplay && $this->deduplicator->isDuplicate($event, $existing)) {
            $this->logIngestion(
                config: $config,
                event: $event,
                status: VoipLogStatus::Success,
                message: 'duplicate_event_filtered',
                payload: $rawPayload ?? $event->rawPayload,
            );

            Log::info('duplicate_event_filtered', [
                'connection_id' => $config->connectionId,
                'call_id' => $event->callId,
                'event_type' => $event->type->value,
                'source' => $event->source->value,
            ]);

            return VoipOperationResult::success(
                data: ['event' => $event->type->value, 'duplicate' => true],
                message: 'Duplicate VoIP event filtered.',
            );
        }

        if ($callLog = $this->dispatcher->toCallLogData(
            organizationId: $config->organizationId,
            connectionId: $config->connectionId,
            providerCode: $config->providerCode->value,
            event: $event,
        )) {
            $this->callLogs->upsert($callLog);
        }

        $this->dispatcher->dispatch($config->organizationId, $config->connectionId, $event);

        $logKey = $event->source === VoipEventSource::Polling
            ? 'polling_detected_call'
            : 'webhook_received';

        $this->logIngestion(
            config: $config,
            event: $event,
            status: VoipLogStatus::Success,
            message: $logKey,
            payload: $rawPayload ?? $event->rawPayload,
        );

        Log::info($logKey, [
            'connection_id' => $config->connectionId,
            'organization_id' => $config->organizationId,
            'call_id' => $event->callId,
            'event_type' => $event->type->value,
            'provider' => $event->provider ?? $config->providerCode->value,
            'source' => $event->source->value,
        ]);

        return VoipOperationResult::success(
            data: ['event' => $event->type->value, 'source' => $event->source->value],
            message: 'VoIP event ingested successfully.',
        );
    }

    private function logIngestion(
        VoipConnectionConfig $config,
        NormalizedWebhookEvent $event,
        VoipLogStatus $status,
        string $message,
        ?array $payload = null,
    ): void {
        if ($event->source === VoipEventSource::Polling) {
            $this->logs->logSync(
                connectionId: $config->connectionId,
                operation: VoipOperation::SyncData,
                status: $status,
                payload: $payload,
                message: $message,
                recordsProcessed: 1,
            );

            return;
        }

        $this->logs->logWebhook(
            connectionId: $config->connectionId,
            status: $status,
            payload: $payload,
            message: $message,
            eventType: $event->type->value,
        );
    }
}
