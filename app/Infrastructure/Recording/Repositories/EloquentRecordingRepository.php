<?php

namespace App\Infrastructure\Recording\Repositories;

use App\Domain\Recording\Contracts\RecordingRepositoryInterface;
use App\Domain\Recording\DTOs\RecordingData;
use App\Models\CallRecording;

class EloquentRecordingRepository implements RecordingRepositoryInterface
{
    public function create(RecordingData $data): int
    {
        $uploadedAt = now();

        return CallRecording::query()->create([
            'call_id' => $data->callId,
            'source_url' => $data->sourceUrl,
            'storage_disk' => $data->storageDisk ?? config('recordings.disk', 'local'),
            'storage_path' => $data->storagePath,
            'mime_type' => $data->mimeType,
            'file_size_bytes' => $data->fileSizeBytes,
            'duration_seconds' => $data->durationSeconds,
            'channels' => $data->channels,
            'status' => $data->status,
            'uploaded_at' => $uploadedAt,
            'expires_at' => null,
            'is_expired' => false,
        ])->id;
    }

    public function update(int $recordingId, RecordingData $data): void
    {
        $attributes = [
            'storage_disk' => $data->storageDisk,
            'storage_path' => $data->storagePath,
            'mime_type' => $data->mimeType,
            'file_size_bytes' => $data->fileSizeBytes,
            'status' => $data->status,
            'error_message' => $data->status === 'failed' ? ($data->storagePath ?? 'Download failed') : null,
            'downloaded_at' => $data->status === 'completed' ? now() : null,
        ];

        if ($data->status === 'completed' && $data->storagePath) {
            $attributes['uploaded_at'] = now();
            $attributes['expires_at'] = null;
            $attributes['is_expired'] = false;
        }

        CallRecording::query()->whereKey($recordingId)->update(array_filter(
            $attributes,
            fn (mixed $value) => $value !== null,
        ));
    }

    public function findByCallId(int $callId): ?RecordingData
    {
        $recording = CallRecording::query()->where('call_id', $callId)->latest()->first();

        if (! $recording) {
            return null;
        }

        return new RecordingData(
            callId: $recording->call_id,
            sourceUrl: $recording->source_url,
            storageDisk: $recording->storage_disk,
            storagePath: $recording->storage_path,
            mimeType: $recording->mime_type,
            fileSizeBytes: $recording->file_size_bytes,
            durationSeconds: $recording->duration_seconds,
            channels: $recording->channels,
            status: $recording->status,
            id: $recording->id,
        );
    }
}
