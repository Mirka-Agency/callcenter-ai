<?php

namespace App\Application\Intelligence\Services;

use App\Application\Call\Services\CallEmployeeResolver;
use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Call;
use App\Services\AiBillingService;
use App\Services\CallProcessingTracker;
use App\Support\UnconnectedCallSignals;

class CallAnalysisQueueService
{
    public function __construct(
        private CallProcessingTracker $tracker,
        private AiBillingService $billing,
        private CallEmployeeResolver $employeeResolver,
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

        if (! $this->matchesDefinedExtension($call)) {
            return false;
        }

        if ($this->shouldSkipAnalysis($call)) {
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

    /**
     * VoIP calls are analyzed only when the log resolves to an extension
     * defined on an employee. Support-line and caller numbers do not qualify.
     */
    private function matchesDefinedExtension(Call $call): bool
    {
        if ($call->source !== ConversationSource::Voip) {
            return true;
        }

        $log = $call->voipCallLog;

        if ($log === null) {
            return true;
        }

        return $this->employeeResolver->resolveFromCallLog($log) !== null;
    }

    /**
     * Ring time, a carrier tone, or an explicit no-answer disposition is not a conversation.
     */
    private function lacksConnectedConversation(Call $call): bool
    {
        $payload = $this->conversationPayload($call);

        foreach ($this->statusCandidates($call, $payload) as $status) {
            if (UnconnectedCallSignals::isUnconnectedStatus($status)) {
                return true;
            }
        }

        return UnconnectedCallSignals::explicitTalkSeconds($payload) === 0;
    }

    private function analyzableSeconds(Call $call): ?int
    {
        $talkSeconds = UnconnectedCallSignals::explicitTalkSeconds($this->conversationPayload($call));

        if ($talkSeconds !== null) {
            return $talkSeconds;
        }

        $duration = $call->duration_seconds ?? $call->voipCallLog?->duration;

        return $duration === null ? null : (int) $duration;
    }

    /** @return array<string, mixed> */
    private function conversationPayload(Call $call): array
    {
        $metadata = is_array($call->metadata) ? $call->metadata : [];
        $raw = is_array($call->voipCallLog?->raw_payload) ? $call->voipCallLog->raw_payload : [];

        return array_replace($raw, $metadata);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function statusCandidates(Call $call, array $payload): array
    {
        $logStatus = $call->voipCallLog?->status;
        $logStatus = $logStatus instanceof \BackedEnum ? $logStatus->value : $logStatus;

        return array_values(array_filter([
            is_string($call->status) ? $call->status : null,
            is_string($logStatus) ? $logStatus : null,
            isset($payload['disposition']) ? (string) $payload['disposition'] : null,
            isset($payload['call_status']) ? (string) $payload['call_status'] : null,
        ], fn (?string $status) => $status !== null && $status !== ''));
    }

    public function shouldSkipAnalysis(Call $call): bool
    {
        if ($call->source !== ConversationSource::Voip) {
            return false;
        }

        if ($this->lacksConnectedConversation($call)) {
            return true;
        }

        $minDuration = (int) config('intelligence.min_analyzable_duration_seconds', 10);

        if ($minDuration <= 0) {
            return false;
        }

        $duration = $this->analyzableSeconds($call);

        if ($duration === null) {
            return false;
        }

        return $duration < $minDuration;
    }

    public function markSkipped(Call $call): void
    {
        if ($call->processing_status === CallProcessingStatus::Skipped) {
            return;
        }

        $duration = $this->analyzableSeconds($call);
        $minDuration = (int) config('intelligence.min_analyzable_duration_seconds', 10);

        $reason = $this->lacksConnectedConversation($call)
            ? 'تماس برقرار نشده یا بدون مکالمه بود؛ ضبط و تحلیل انجام نشد.'
            : "مدت تماس ({$duration} ثانیه) کمتر از حداقل قابل تحلیل ({$minDuration} ثانیه) است؛ ضبط و تحلیل انجام نشد.";

        $call->update([
            'processing_status' => CallProcessingStatus::Skipped,
            'processing_error' => $reason,
        ]);
    }
}
