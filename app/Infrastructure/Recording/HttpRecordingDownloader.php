<?php

namespace App\Infrastructure\Recording;

use App\Domain\Recording\Contracts\RecordingDownloaderInterface;
use App\Domain\Recording\ValueObjects\RecordingDownloadResult;
use App\Infrastructure\Voip\Support\DatedMonitorRecordingUrl;
use App\Models\Call;
use App\Services\RecordingStorage;
use Illuminate\Support\Facades\Http;

class HttpRecordingDownloader implements RecordingDownloaderInterface
{
    private const MIN_AUDIO_BYTES = 2048;

    public function __construct(
        private RecordingStorage $recordingStorage,
    ) {}

    public function download(string $url, int $callId): RecordingDownloadResult
    {
        try {
            $lastError = 'Failed to download recording.';

            foreach ($this->candidatesFor($url, $callId) as $candidate) {
                $response = Http::timeout(120)->get($candidate);

                if (! $response->successful()) {
                    $lastError = 'Failed to download recording.';

                    continue;
                }

                $body = $response->body();
                $mimeType = $response->header('Content-Type') ?? 'audio/mpeg';

                if (! $this->looksLikeAudio($body, $mimeType)) {
                    $lastError = 'Recording URL did not return audio.';

                    continue;
                }

                $path = "recordings/{$callId}/".now()->format('YmdHis').'.mp3';
                $this->recordingStorage->put($path, $body, $mimeType);

                return new RecordingDownloadResult(
                    success: true,
                    storagePath: $path,
                    storageDisk: $this->recordingStorage->disk(),
                    mimeType: $mimeType,
                    fileSizeBytes: strlen($body),
                );
            }

            return new RecordingDownloadResult(success: false, error: $lastError);
        } catch (\Throwable $e) {
            return new RecordingDownloadResult(success: false, error: $e->getMessage());
        }
    }

    /** @return list<string> */
    private function candidatesFor(string $url, int $callId): array
    {
        $candidates = DatedMonitorRecordingUrl::downloadCandidates($url);
        $resolved = $this->resolveFromMonitorListing($url, $callId);

        if ($resolved !== null) {
            array_unshift($candidates, $resolved);
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function resolveFromMonitorListing(string $url, int $callId): ?string
    {
        try {
            $call = Call::query()->with('voipCallLog')->find($callId);
        } catch (\Throwable) {
            return null;
        }
        $uniqueId = $call?->voipCallLog?->external_call_id
            ?? $call?->external_call_id;

        if (! is_string($uniqueId) || $uniqueId === '') {
            return null;
        }

        $occurred = $call?->started_at ?? $call?->created_at ?? now();
        $days = [
            $occurred->copy()->timezone('Asia/Tehran'),
            $occurred->copy()->timezone('UTC'),
        ];

        foreach ($days as $day) {
            $listingUrl = DatedMonitorRecordingUrl::listingUrl($url, $day);

            if ($listingUrl === null) {
                continue;
            }

            $response = Http::timeout(20)->get($listingUrl);

            if (! $response->successful()) {
                continue;
            }

            $filename = DatedMonitorRecordingUrl::filenameForUniqueId($response->body(), $uniqueId);

            if ($filename !== null) {
                return DatedMonitorRecordingUrl::joinListingUrl($listingUrl, $filename);
            }
        }

        return null;
    }

    private function looksLikeAudio(string $body, string $mimeType): bool
    {
        if (strlen($body) < self::MIN_AUDIO_BYTES) {
            return false;
        }

        $mime = strtolower($mimeType);

        if (str_contains($mime, 'text/html') || str_contains($mime, 'text/plain') || str_contains($mime, 'application/json')) {
            return false;
        }

        $head = strtolower(ltrim(substr($body, 0, 64)));

        return ! str_starts_with($head, '<!doctype')
            && ! str_starts_with($head, '<html')
            && ! str_contains($head, 'index of');
    }
}
