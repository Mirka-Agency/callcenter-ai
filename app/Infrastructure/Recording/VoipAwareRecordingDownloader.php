<?php

namespace App\Infrastructure\Recording;

use App\Application\Voip\Services\VoipConnectionResolver;
use App\Domain\Recording\Contracts\RecordingDownloaderInterface;
use App\Domain\Recording\ValueObjects\RecordingDownloadResult;
use App\Infrastructure\Voip\Adapters\SimotelVoipAdapter;
use App\Models\Call;
use App\Services\RecordingStorage;

class VoipAwareRecordingDownloader implements RecordingDownloaderInterface
{
    public function __construct(
        private HttpRecordingDownloader $httpDownloader,
        private VoipConnectionResolver $voipConnections,
        private RecordingStorage $recordingStorage,
    ) {}

    public function download(string $url, int $callId): RecordingDownloadResult
    {
        if (str_starts_with($url, SimotelVoipAdapter::RECORDING_URL_PREFIX)) {
            return $this->downloadSimotelRecording($url, $callId);
        }

        return $this->httpDownloader->download($url, $callId);
    }

    private function downloadSimotelRecording(string $url, int $callId): RecordingDownloadResult
    {
        try {
            $call = Call::query()->find($callId);

            if (! $call?->organization_voip_connection_id) {
                return new RecordingDownloadResult(
                    success: false,
                    error: 'Call is missing a VoIP connection for Simotel recording download.',
                );
            }

            [, $adapter] = $this->voipConnections->resolveByConnectionId(
                (int) $call->organization_voip_connection_id,
            );

            if (! $adapter instanceof SimotelVoipAdapter) {
                return new RecordingDownloadResult(
                    success: false,
                    error: 'VoIP connection adapter does not support Simotel recordings.',
                );
            }

            $result = $adapter->getCallRecording($url);

            if (! $result->success) {
                return new RecordingDownloadResult(
                    success: false,
                    error: $result->error ?? 'Failed to download Simotel recording.',
                );
            }

            $body = $result->data['body'] ?? null;

            if (! is_string($body) || $body === '') {
                return new RecordingDownloadResult(
                    success: false,
                    error: 'Simotel recording response was empty.',
                );
            }

            $path = "recordings/{$callId}/".now()->format('YmdHis').'.mp3';
            $mimeType = is_string($result->data['mime_type'] ?? null)
                ? $result->data['mime_type']
                : 'audio/mpeg';

            $this->recordingStorage->put($path, $body, $mimeType);

            return new RecordingDownloadResult(
                success: true,
                storagePath: $path,
                storageDisk: $this->recordingStorage->disk(),
                mimeType: $mimeType,
                fileSizeBytes: strlen($body),
            );
        } catch (\Throwable $e) {
            return new RecordingDownloadResult(success: false, error: $e->getMessage());
        }
    }
}
