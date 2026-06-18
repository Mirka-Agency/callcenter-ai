<?php

namespace App\Services;

use App\Models\CallRecording;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RecordingUrlService
{
    public function __construct(
        private RecordingRetentionService $retention,
        private RecordingStorage $storage,
    ) {}

    public function resolve(?CallRecording $recording, ?string $fallbackUrl = null): ?string
    {
        if (! $recording?->storage_path) {
            return $fallbackUrl;
        }

        if ($this->retention->isExpired($recording)) {
            $this->retention->purgeIfDue($recording);

            return null;
        }

        $diskName = $this->storage->resolveDisk(
            $recording->storage_path,
            $recording->storage_disk,
        );

        if ($diskName === null) {
            Log::warning('Recording file missing for playback', [
                'recording_id' => $recording->id,
                'disk' => $recording->storage_disk ?: config('recordings.disk', 'local'),
                'path' => $recording->storage_path,
            ]);

            return $fallbackUrl;
        }

        $disk = Storage::disk($diskName);
        $ttlMinutes = (int) config('recordings.playback_url_ttl_minutes', 120);
        $options = [];

        if ($recording->mime_type) {
            $options['ResponseContentType'] = $recording->mime_type;
        }

        if ($disk->providesTemporaryUrls()) {
            $expiresAt = $recording->expires_at ?? now()->addMinutes($ttlMinutes);
            $signatureExpiresAt = $expiresAt->isFuture()
                ? $expiresAt->min(now()->addMinutes($ttlMinutes))
                : now()->addMinutes($ttlMinutes);

            return $disk->temporaryUrl(
                $recording->storage_path,
                $signatureExpiresAt,
                $options,
            );
        }

        return $disk->url($recording->storage_path);
    }
}
