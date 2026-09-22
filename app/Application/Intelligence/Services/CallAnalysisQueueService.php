<?php

namespace App\Application\Intelligence\Services;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Domain\Voip\Enums\CallStatus;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Call;
use App\Services\AiBillingService;
use App\Services\CallProcessingTracker;

class CallAnalysisQueueService
{
    /** @var list<string> */
    private const NON_ANALYZABLE_STATUSES = [
        CallStatus::Missed->value,
        CallStatus::Busy->value,
        CallStatus::Failed->value,
        CallStatus::Cancelled->value,
        CallStatus::Initiated->value,
        CallStatus::Ringing->value,
    ];

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

        if (! $forceReanalyze && $this->shouldSkipAnalysis($call)) {
            $this->markSkipped($call);

            return false;
        }

        if (! $forceReanalyze) {
            if ($call->analyses()->exists()) {
                return false;
            }

            if (in_array($call->processing_status, [
                CallProcessingStatus::Analyzed,
                CallProcessingStatus::Failed,
                CallProcessingStatus::Skipped,
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

    public function shouldSkipAnalysis(Call $call): bool
    {
        $status = strtolower((string) ($call->status ?? $call->voipCallLog?->status ?? ''));

        if (in_array($status, self::NON_ANALYZABLE_STATUSES, true)) {
            return true;
        }

        $minDuration = (int) config('intelligence.min_analyzable_duration_seconds', 10);

        if ($minDuration <= 0) {
            return false;
        }

        $duration = $call->duration_seconds ?? $call->voipCallLog?->duration;

        if ($duration === null) {
            return false;
        }

        return (int) $duration < $minDuration;
    }

    public function markSkipped(Call $call): void
    {
        if ($call->processing_status === CallProcessingStatus::Skipped) {
            return;
        }

        $status = strtolower((string) ($call->status ?? $call->voipCallLog?->status ?? ''));
        $duration = $call->duration_seconds ?? $call->voipCallLog?->duration;
        $minDuration = (int) config('intelligence.min_analyzable_duration_seconds', 10);

        $reason = in_array($status, self::NON_ANALYZABLE_STATUSES, true)
            ? 'تماس برقرار نشده یا بدون مکالمه بود؛ ضبط و تحلیل انجام نشد.'
            : "مدت تماس ({$duration} ثانیه) کمتر از حداقل قابل تحلیل ({$minDuration} ثانیه) است؛ ضبط و تحلیل انجام نشد.";

        $call->update([
            'processing_status' => CallProcessingStatus::Skipped,
            'processing_error' => $reason,
        ]);
    }
}
