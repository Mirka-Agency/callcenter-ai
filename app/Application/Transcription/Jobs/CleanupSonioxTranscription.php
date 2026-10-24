<?php

namespace App\Application\Transcription\Jobs;

use App\Domain\Transcription\Contracts\TranscriptionClient;
use App\Models\CallTranscript;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CleanupSonioxTranscription implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 270;

    public function __construct(public int $transcriptId)
    {
        $this->onQueue((string) config('queue.names.analysis'));
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(TranscriptionClient $client): void
    {
        $transcript = CallTranscript::query()->find($this->transcriptId);
        if (! $transcript || $transcript->remote_cleaned_at) {
            return;
        }
        if (! in_array($transcript->status, [CallTranscript::STATUS_COMPLETED, CallTranscript::STATUS_FAILED], true)) {
            throw new \RuntimeException('Cannot clean up a transcription that is still processing.');
        }
        if ($transcript->remote_transcription_id) {
            $client->deleteTranscription($transcript->remote_transcription_id);
        }
        if ($transcript->remote_file_id) {
            $client->deleteFile($transcript->remote_file_id);
        }
        $transcript->update(['remote_cleaned_at' => now()]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Soniox cleanup failed; remote resources require attention.', [
            'transcript_id' => $this->transcriptId,
            'exception_type' => $exception ? $exception::class : null,
        ]);
    }
}
