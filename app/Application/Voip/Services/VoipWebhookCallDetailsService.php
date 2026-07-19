<?php

namespace App\Application\Voip\Services;

use App\Models\VoipCallLog;
use App\Models\VoipWebhookLog;
use App\Support\WebhookPayloadPresenter;

class VoipWebhookCallDetailsService
{
    public function __construct(
        private VoipConnectionResolver $resolver,
        private WebhookPayloadPresenter $presenter,
    ) {}

    /** @return array<string, mixed> */
    public function forWebhookLog(VoipWebhookLog $log): array
    {
        $payload = is_array($log->payload) ? $log->payload : [];
        $callId = $this->extractCallId($payload);

        $localLog = null;
        if ($callId !== null) {
            $localLog = VoipCallLog::query()
                ->where('organization_voip_connection_id', $log->organization_voip_connection_id)
                ->where('external_call_id', $callId)
                ->first();
        }

        $api = [
            'attempted' => false,
            'success' => false,
            'message' => null,
            'error' => null,
            'rows' => [],
            'raw' => null,
        ];

        if ($callId !== null) {
            $api['attempted'] = true;

            try {
                [, $adapter] = $this->resolver->resolveByConnectionId(
                    (int) $log->organization_voip_connection_id,
                );
                $result = $adapter->getCallDetails($callId);
                $api['success'] = $result->success;
                $api['message'] = $result->message;
                $api['error'] = $result->error;
                $api['rows'] = is_array($result->data['rows'] ?? null) ? $result->data['rows'] : [];
                $api['raw'] = $result->data['raw'] ?? $result->data;
            } catch (\Throwable $e) {
                $api['error'] = $e->getMessage();
            }
        } else {
            $api['error'] = __('filament.misc.webhook_call_details_missing_cuid');
        }

        return [
            'call_id' => $callId,
            'highlights' => $this->presenter->highlights($payload),
            'webhook_payload' => $this->presenter->format($payload),
            'local_call_log' => $localLog ? [
                'id' => $localLog->id,
                'external_call_id' => $localLog->external_call_id,
                'direction' => $localLog->direction?->value,
                'source_number' => $localLog->source_number,
                'destination_number' => $localLog->destination_number,
                'status' => $localLog->status?->value,
                'recording_url' => $localLog->recording_url,
                'duration' => $localLog->duration,
                'resolved_extension' => $localLog->raw_payload['resolved_extension'] ?? null,
                'started_at' => optional($localLog->started_at)?->toDateTimeString(),
                'ended_at' => optional($localLog->ended_at)?->toDateTimeString(),
            ] : null,
            'api' => $api,
            'diagnosis' => $this->diagnose($payload, $localLog, $api),
        ];
    }

    /** @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $api
     * @return list<string>
     */
    private function diagnose(array $payload, ?VoipCallLog $localLog, array $api): array
    {
        $notes = [];

        $dst = (string) ($payload['dst'] ?? '');
        $did = (string) ($payload['did'] ?? '');
        $record = $payload['record'] ?? null;

        if ($dst !== '' && $did !== '' && $dst === $did) {
            $notes[] = __('filament.misc.webhook_call_details_diagnosis_did_only');
        }

        if (! is_string($record) || trim($record) === '') {
            $notes[] = __('filament.misc.webhook_call_details_diagnosis_no_record');
        }

        if ($localLog && blank($localLog->recording_url)) {
            $notes[] = __('filament.misc.webhook_call_details_diagnosis_no_local_recording');
        }

        if ($api['attempted'] && ! $api['success']) {
            $notes[] = __('filament.misc.webhook_call_details_diagnosis_api_failed', [
                'error' => $api['error'] ?? __('filament.misc.em_dash'),
            ]);
        }

        if ($api['success'] && ($api['rows'] ?? []) === []) {
            $notes[] = __('filament.misc.webhook_call_details_diagnosis_api_empty');
        }

        return $notes;
    }

    /** @param  array<string, mixed>  $payload */
    private function extractCallId(array $payload): ?string
    {
        foreach (['cuid', 'unique_id', 'uniqueid', 'call_id'] as $key) {
            if (! empty($payload[$key]) && is_scalar($payload[$key])) {
                return (string) $payload[$key];
            }
        }

        return null;
    }
}
