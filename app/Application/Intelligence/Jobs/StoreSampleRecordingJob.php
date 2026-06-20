<?php

namespace App\Application\Intelligence\Jobs;

use App\Domain\Recording\Contracts\RecordingRepositoryInterface;
use App\Domain\Recording\DTOs\RecordingData;
use App\Services\RecordingStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StoreSampleRecordingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $callId,
        public int $recordingId,
        public string $sourcePath,
        public string $storagePath,
        public string $mimeType,
    ) {}

    public function handle(RecordingStorage $storage, RecordingRepositoryInterface $recordings): void
    {
        $storage->putFromLocalPath($this->sourcePath, $this->storagePath, $this->mimeType);

        $recordings->update($this->recordingId, new RecordingData(
            callId: $this->callId,
            storageDisk: $storage->disk(),
            storagePath: $this->storagePath,
            mimeType: $this->mimeType,
            status: 'completed',
        ));
    }

    public function failed(\Throwable $exception): void
    {
        app(RecordingRepositoryInterface::class)->update($this->recordingId, new RecordingData(
            callId: $this->callId,
            storageDisk: app(RecordingStorage::class)->disk(),
            storagePath: $this->storagePath,
            mimeType: $this->mimeType,
            status: 'failed',
        ));
    }
}
