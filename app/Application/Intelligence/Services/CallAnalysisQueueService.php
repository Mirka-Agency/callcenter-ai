<?php

namespace App\Application\Intelligence\Services;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Call;
use App\Services\AiBillingService;
use App\Services\CallProcessingTracker;

class CallAnalysisQueueService
{
    public function __construct(
        private CallProcessingTracker $tracker,
        private AiBillingService $billing,
    ) {}

    public function dispatchForCall(Call $call, bool $forceReanalyze = false): bool
    {
        $call->loadMissing(['voipCallLog', 'recording']);

        $recordingUrl = $call->voipCallLog?->recording_url
            ?? $call->recording?->source_url;

        $hasLocalRecording = $call->recording
            && $call->recording->status === 'completed'
            && filled($call->recording->storage_path)
            && ! $call->recording->is_expired;

        if (! $recordingUrl && ! $hasLocalRecording) {
            return false;
        }

        if (! $call->organization_user_id) {
            return false;
        }

        if (! $forceReanalyze) {
            if ($call->analyses()->exists()) {
                return false;
            }

            if (in_array($call->processing_status, [
                CallProcessingStatus::Analyzed,
                CallProcessingStatus::Failed,
                CallProcessingStatus::Analyzing,
                CallProcessingStatus::Downloading,
            ], true)) {
                return false;
            }
        }

        $existing = $this->tracker->forCall($call->id);

        if ($existing && in_array($existing->status, [ProcessingJobStatus::Queued, ProcessingJobStatus::Processing, ProcessingJobStatus::Uploading], true)) {
            return false;
        }

        try {
            $this->billing->assertCanAnalyze($call->organization_id);
        } catch (InsufficientWalletBalanceException) {
            return false;
        }

        if ($existing) {
            $this->tracker->requeueForAnalysis($existing);
        } else {
            $job = $this->tracker->startUpload(
                $call,
                $call->voipCallLog?->external_call_id
                    ? 'voip-'.$call->voipCallLog->external_call_id
                    : ($call->displayTitle()),
            );
            $this->tracker->markUploaded($job);
        }

        AnalyzeAudioJob::dispatchChain($call->id, $recordingUrl);

        return true;
    }
}
