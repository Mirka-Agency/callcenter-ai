<?php

namespace App\Services;

use App\Models\CallRecording;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RecordingRetentionService
{
    public function retentionDays(): int
    {
        return max(1, (int) config('recordings.retention_days', 10));
    }

    public function expiresAt(Carbon $from): Carbon
    {
        return $from->copy()->addDays($this->retentionDays());
    }

    public function scheduleExpiryAfterAnalysis(CallRecording $recording): void
    {
        $recording->update([
            'expires_at' => $this->expiresAt(now()),
            'is_expired' => false,
            'expired_at' => null,
        ]);

        Log::info('Recording retention scheduled after analysis', [
            'recording_id' => $recording->id,
            'call_id' => $recording->call_id,
            'expires_at' => $recording->expires_at,
        ]);
    }

    public function isExpired(CallRecording $recording): bool
    {
        if ($recording->is_expired) {
            return true;
        }

        if ($recording->expires_at === null) {
            return false;
        }

        return $recording->expires_at->isPast();
    }

    /** @return array{url: ?string, expired: bool} */
    public function playbackState(?CallRecording $recording, ?string $fallbackUrl = null): array
    {
        if (! $recording) {
            return [
                'url' => $fallbackUrl,
                'expired' => false,
            ];
        }

        if ($this->isExpired($recording)) {
            $this->purgeIfDue($recording);

            return [
                'url' => null,
                'expired' => true,
            ];
        }

        if (! $recording->storage_path) {
            return [
                'url' => $fallbackUrl,
                'expired' => false,
            ];
        }

        return [
            'url' => app(RecordingUrlService::class)->resolve($recording),
            'expired' => false,
        ];
    }

    public function purgeIfDue(CallRecording $recording): bool
    {
        if (! $this->isExpired($recording)) {
            return false;
        }

        if ($recording->is_expired && $recording->storage_path === null) {
            return false;
        }

        $this->purge($recording);

        return true;
    }

    public function purgeDue(): int
    {
        $purged = 0;

        CallRecording::query()
            ->dueForPurge()
            ->orderBy('id')
            ->each(function (CallRecording $recording) use (&$purged): void {
                $this->purge($recording);
                $purged++;
            });

        if ($purged > 0) {
            Log::info('Expired recordings purged', ['count' => $purged]);
        }

        return $purged;
    }

    public function purge(CallRecording $recording): void
    {
        if ($recording->storage_path) {
            $diskName = $recording->storage_disk ?: config('recordings.disk', 'local');

            try {
                Storage::disk($diskName)->delete($recording->storage_path);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete expired recording file', [
                    'recording_id' => $recording->id,
                    'disk' => $diskName,
                    'path' => $recording->storage_path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $recording->update([
            'is_expired' => true,
            'expired_at' => $recording->expired_at ?? now(),
            'storage_path' => null,
            'source_url' => null,
            'status' => 'expired',
        ]);

        Log::info('Recording marked expired and purged from storage', [
            'recording_id' => $recording->id,
            'call_id' => $recording->call_id,
        ]);
    }
}
