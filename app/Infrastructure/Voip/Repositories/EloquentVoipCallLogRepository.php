<?php

namespace App\Infrastructure\Voip\Repositories;

use App\Domain\Voip\Contracts\VoipCallLogRepositoryInterface;
use App\Domain\Voip\DTOs\CallLogData;
use App\Models\VoipCallLog;

class EloquentVoipCallLogRepository implements VoipCallLogRepositoryInterface
{
    public function upsert(CallLogData $data): void
    {
        $existing = VoipCallLog::query()
            ->where('organization_voip_connection_id', $data->connectionId)
            ->where('external_call_id', $data->externalCallId)
            ->first();

        $attributes = $data->toArray();

        // Later events (e.g. CallStarted → CallEnded) often omit fields; never wipe known values with null.
        if ($existing) {
            foreach (['source_number', 'destination_number', 'status', 'started_at', 'ended_at', 'duration', 'recording_url', 'raw_payload'] as $field) {
                if ($attributes[$field] === null || $attributes[$field] === '') {
                    unset($attributes[$field]);
                }
            }

            // Prefer richer terminal statuses over ringing when merging.
            if (isset($attributes['status'], $existing->status)
                && $existing->status?->value === 'completed'
                && in_array($attributes['status'], ['ringing', 'initiated'], true)) {
                unset($attributes['status']);
            }

            $existing->update($attributes);

            return;
        }

        VoipCallLog::query()->create($attributes);
    }

    public function findByExternalCallId(int $connectionId, string $externalCallId): ?object
    {
        return VoipCallLog::query()
            ->where('organization_voip_connection_id', $connectionId)
            ->where('external_call_id', $externalCallId)
            ->first();
    }
}
