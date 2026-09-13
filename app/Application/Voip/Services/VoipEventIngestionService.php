<?php

namespace App\Application\Voip\Services;

use App\Application\Call\Services\CallEmployeeResolver;
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
        private CallEmployeeResolver $employeeResolver,
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
            [$extension, $employeeId] = $this->resolveRouting($config, $event, $existing);

            $this->logIngestion(
                config: $config,
                event: $event,
                status: VoipLogStatus::Success,
                message: 'duplicate_event_filtered',
                payload: $rawPayload ?? $event->rawPayload,
                resolvedExtension: $extension,
                organizationUserId: $employeeId,
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

        $callLog = null;
        if ($callLogData = $this->dispatcher->toCallLogData(
            organizationId: $config->organizationId,
            connectionId: $config->connectionId,
            providerCode: $config->providerCode->value,
            event: $event,
        )) {
            $this->callLogs->upsert($callLogData);

            if ($event->callId) {
                $callLog = VoipCallLog::query()
                    ->where('organization_voip_connection_id', $config->connectionId)
                    ->where('external_call_id', $event->callId)
                    ->first();
            }
        }

        $this->dispatcher->dispatch($config->organizationId, $config->connectionId, $event);

        $logKey = match ($event->source) {
            VoipEventSource::Polling => 'polling_detected_call',
            VoipEventSource::Ami => 'ami_event_received',
            default => 'webhook_received',
        };

        [$extension, $employeeId] = $this->resolveRouting($config, $event, $callLog ?? $existing);

        $this->logIngestion(
            config: $config,
            event: $event,
            status: VoipLogStatus::Success,
            message: $logKey,
            payload: $rawPayload ?? $event->rawPayload,
            resolvedExtension: $extension,
            organizationUserId: $employeeId,
        );

        Log::info($logKey, [
            'connection_id' => $config->connectionId,
            'organization_id' => $config->organizationId,
            'call_id' => $event->callId,
            'event_type' => $event->type->value,
            'provider' => $event->provider ?? $config->providerCode->value,
            'source' => $event->source->value,
            'resolved_extension' => $extension,
            'organization_user_id' => $employeeId,
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
        ?string $resolvedExtension = null,
        ?int $organizationUserId = null,
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
            resolvedExtension: $resolvedExtension,
            organizationUserId: $organizationUserId,
        );
    }

    /** @return array{0: ?string, 1: ?int} */
    private function resolveRouting(
        VoipConnectionConfig $config,
        NormalizedWebhookEvent $event,
        ?VoipCallLog $callLog,
    ): array {
        $extension = $this->normalizeExtension($event->extension);

        if ($extension === null) {
            foreach ([
                $event->rawPayload['resolved_extension'] ?? null,
                $event->rawPayload['extension'] ?? null,
                $event->rawPayload['agent_extension'] ?? null,
                $event->rawPayload['internal_number'] ?? null,
                $event->rawPayload['exten'] ?? null,
            ] as $candidate) {
                $extension = $this->normalizeExtension($candidate);

                if ($extension !== null) {
                    break;
                }
            }
        }

        $employeeId = null;

        if ($callLog !== null) {
            if ($extension === null) {
                $extension = $this->employeeResolver->extensionCandidates($callLog)[0] ?? null;
            }

            $employeeId = $this->employeeResolver->resolveFromCallLog($callLog);
        }

        if ($employeeId === null && $extension !== null) {
            $employeeId = $this->employeeResolver->resolveByExtension(
                organizationId: $config->organizationId,
                voipConnectionId: $config->connectionId,
                extension: $extension,
            );
        }

        return [$extension, $employeeId];
    }

    private function normalizeExtension(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
