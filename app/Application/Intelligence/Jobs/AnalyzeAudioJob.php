<?php

namespace App\Application\Intelligence\Jobs;

use App\Application\Llm\AnalysisManager;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Domain\Recording\Contracts\RecordingDownloaderInterface;
use App\Domain\Recording\Contracts\RecordingRepositoryInterface;
use App\Domain\Recording\DTOs\RecordingData;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\VoipCallLog;
use App\Services\CallProcessingTracker;
use App\Services\RecordingRetentionService;
use App\Services\RecordingStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class AnalyzeAudioJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 1800;

    public function __construct(
        public int $callId,
        public ?string $recordingUrl = null,
    ) {}

    public function uniqueId(): string
    {
        return 'analyze-call-'.$this->callId;
    }

    public function handle(
        AnalysisManager $analysis,
        RecordingRepositoryInterface $recordings,
        RecordingDownloaderInterface $downloader,
        CallProcessingTracker $tracker,
        RecordingStorage $recordingStorage,
        RecordingRetentionService $retention,
    ): void {
        $call = Call::query()->findOrFail($this->callId);
        $job = $tracker->forCall($call->id);

        $call->update([
            'processing_status' => CallProcessingStatus::Downloading,
            'processing_error' => null,
        ]);

        if ($job) {
            $tracker->markProcessingStarted($job);
        }

        try {
            $this->ensureRecording($call, $recordings, $downloader, $recordingStorage);

            $call->update(['processing_status' => CallProcessingStatus::Analyzing]);

            if ($job) {
                $tracker->markSendingToAi($job);
                $tracker->markWaitingForAi($job);
            }

            $analysis::forOrganization($call->organization_id)
                ->analyzeCall($this->callId);

            if ($job) {
                $tracker->markProcessingResult($job);
                $tracker->markCompleted($job);
            }

            $call->update(['processing_status' => CallProcessingStatus::Analyzed]);

            $this->scheduleRetentionAfterAnalysis($call->id, $retention);
        } catch (\Throwable $e) {
            $this->markPermanentFailure($call, $job, $tracker, $e);
            $this->failWithoutRetry($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $call = Call::query()->find($this->callId);

        if (! $call) {
            return;
        }

        $tracker = app(CallProcessingTracker::class);
        $job = $tracker->forCall($this->callId);

        $this->markPermanentFailure($call, $job, $tracker, $exception);
    }

    private function failWithoutRetry(\Throwable $e): void
    {
        if ($this->job) {
            $this->fail($e);

            return;
        }

        throw $e;
    }

    private function markPermanentFailure(
        Call $call,
        ?\App\Models\CallProcessingJob $job,
        CallProcessingTracker $tracker,
        \Throwable $e,
    ): void {
        $call->update([
            'processing_status' => CallProcessingStatus::Failed,
            'processing_error' => $e->getMessage(),
        ]);

        if ($job && $job->status !== ProcessingJobStatus::Failed) {
            $tracker->markFailed($job, $e->getMessage());
        }
    }

    private function ensureRecording(
        Call $call,
        RecordingRepositoryInterface $recordings,
        RecordingDownloaderInterface $downloader,
        RecordingStorage $recordingStorage,
    ): void {
        $existing = $recordings->findByCallId($call->id);

        if ($existing?->status === 'completed' && $existing->storagePath) {
            $recordingStorage->assertExists($existing->storagePath, $existing->storageDisk);

            return;
        }

        $url = $this->recordingUrl
            ?? $call->voipCallLog?->recording_url
            ?? VoipCallLog::query()->find($call->voip_call_log_id)?->recording_url;

        if (! $url) {
            throw new \RuntimeException('No recording URL available for call.');
        }

        $recordingId = $existing?->id ?? $recordings->create(new RecordingData(
            callId: $call->id,
            sourceUrl: $url,
            status: 'downloading',
        ));

        $result = $downloader->download($url, $call->id);

        if (! $result->success) {
            $recordings->update($recordingId, new RecordingData(
                callId: $call->id,
                sourceUrl: $url,
                status: 'failed',
            ));

            throw new \RuntimeException($result->error ?? 'Recording download failed.');
        }

        $recordings->update($recordingId, new RecordingData(
            callId: $call->id,
            sourceUrl: $url,
            storageDisk: $result->storageDisk ?? config('recordings.disk', 'local'),
            storagePath: $result->storagePath,
            mimeType: $result->mimeType,
            fileSizeBytes: $result->fileSizeBytes,
            status: 'completed',
            id: $recordingId,
        ));

        $recordingStorage->assertExists($result->storagePath, $result->storageDisk ?? config('recordings.disk', 'local'));
    }

    private function scheduleRetentionAfterAnalysis(int $callId, RecordingRetentionService $retention): void
    {
        $recording = CallRecording::query()->where('call_id', $callId)->latest()->first();

        if ($recording) {
            $retention->scheduleExpiryAfterAnalysis($recording);
        }
    }

    public static function dispatchChain(int $callId, ?string $recordingUrl = null): void
    {
        self::buildChain($callId, $recordingUrl)->dispatch();
    }

    public static function dispatchChainSync(int $callId, ?string $recordingUrl = null): void
    {
        self::dispatchSync($callId, $recordingUrl);
        UpdateEmployeeMetricsJob::dispatchSync($callId);
        SyncCrmJob::dispatchSync($callId);
    }

    private static function buildChain(int $callId, ?string $recordingUrl = null): \Illuminate\Foundation\Bus\PendingChain
    {
        return Bus::chain([
            new self($callId, $recordingUrl),
            new UpdateEmployeeMetricsJob($callId),
            new SyncCrmJob($callId),
        ]);
    }
}
