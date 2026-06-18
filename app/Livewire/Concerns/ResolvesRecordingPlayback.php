<?php

namespace App\Livewire\Concerns;

use App\Models\CallRecording;
use App\Services\RecordingRetentionService;

trait ResolvesRecordingPlayback
{
    public ?string $cachedRecordingPlaybackUrl = null;

    /** @return array{url: ?string, expired: bool} */
    protected function recordingPlaybackState(?CallRecording $recording, ?string $fallbackUrl = null): array
    {
        return app(RecordingRetentionService::class)->playbackState($recording, $fallbackUrl);
    }

    /** @return array{url: ?string, expired: bool} */
    protected function stableRecordingPlaybackState(?CallRecording $recording, ?string $fallbackUrl = null): array
    {
        $state = $this->recordingPlaybackState($recording, $fallbackUrl);

        if ($state['expired'] || $state['url'] === null) {
            return $state;
        }

        if ($this->cachedRecordingPlaybackUrl === null) {
            $this->cachedRecordingPlaybackUrl = $state['url'];
        }

        return [
            'url' => $this->cachedRecordingPlaybackUrl,
            'expired' => false,
        ];
    }

    protected function recordingPlaybackUrl(?CallRecording $recording, ?string $fallbackUrl = null): ?string
    {
        return $this->recordingPlaybackState($recording, $fallbackUrl)['url'];
    }
}
