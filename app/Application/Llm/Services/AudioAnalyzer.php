<?php

namespace App\Application\Llm\Services;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Contracts\ConversationAnalysisRepositoryInterface;
use App\Domain\Llm\Contracts\LlmProviderInterface;
use App\Domain\Llm\DTOs\AnalysisResultData;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\DTOs\LlmConnectionConfig;
use App\Domain\Llm\DTOs\PromptContextData;
use App\Domain\Llm\DTOs\TranscriptionRequestData;
use App\Domain\Llm\Events\AnalysisFailed;
use App\Domain\Llm\Events\ConversationAnalyzed;
use App\Domain\Llm\Exceptions\LlmTransientException;
use App\Infrastructure\Llm\LlmOutboundGuard;
use App\Models\Call;
use App\Models\CallTranscript;
use App\Models\LlmModel;
use App\Models\OrganizationUser;
use App\Models\VoipCallLog;
use App\Services\AiBillingService;
use App\Services\CallProcessingTracker;
use App\Services\LlmModelResolver;
use App\Services\RecordingUrlService;

class AudioAnalyzer
{
    public function __construct(
        private ConversationAnalysisRepositoryInterface $analyses,
        private AiBillingService $billing,
        private LlmConnectionResolver $connections,
        private LlmModelResolver $models,
    ) {}

    public function analyze(
        int $callId,
        LlmConnectionConfig $config,
        LlmProviderInterface $provider,
        ?string $model = null,
    ): AnalysisResultData {
        app(LlmOutboundGuard::class)->assertRemoteAnalysisEnabled();

        $call = Call::query()->with(['recording', 'organization'])->findOrFail($callId);

        $this->billing->assertCanAnalyze($call->organization_id);

        $llmModel = $this->billing->resolveModel($call->organization_id);
        $modelKey = $model ?? $llmModel->model_key;

        $callLog = $call->voip_call_log_id
            ? VoipCallLog::query()->find($call->voip_call_log_id)
            : null;

        $employee = $call->organization_user_id
            ? OrganizationUser::query()->find($call->organization_user_id)
            : null;

        $callDirection = $this->resolveCallDirection($call, $callLog);

        $customerNumber = $callDirection === 'inbound'
            ? $callLog?->source_number
            : $callLog?->destination_number;

        $callDuration = $callLog?->duration;

        if ($call->isManualUpload()) {
            $customerNumber = $call->customer_phone ?? $customerNumber;
            $callDuration = $call->duration_seconds ?? $call->recording?->duration_seconds ?? $callDuration;
        }

        $context = new PromptContextData(
            employeeName: $employee?->full_name,
            department: $employee?->department,
            position: $employee?->position,
            callDirection: $callDirection,
            callDurationSeconds: $callDuration,
            customerNumber: $customerNumber,
            title: $call->title,
            customerName: $call->customer_name,
            category: $call->category,
            notes: $call->notes,
            organizationName: $call->organization?->title,
            organizationBusinessContext: $call->organization?->business_context,
            agentRole: $employee?->position,
        );

        $recording = $call->recording;
        $transcriptionModel = $this->models->resolveTranscriptionForOrganization($call->organization_id);
        $transcriptionProvider = $provider;
        $requestedTranscriptionModel = $config->settings->transcriptionModel;
        $sendAudioFile = (bool) $llmModel->sends_audio_file;

        if ($transcriptionModel instanceof LlmModel) {
            [, $transcriptionProvider] = $this->connections->resolveForModel(
                $call->organization_id,
                $transcriptionModel,
                $transcriptionModel->model_key,
            );
            $requestedTranscriptionModel = $transcriptionModel->model_key;
            $sendAudioFile = (bool) $transcriptionModel->sends_audio_file;
        }

        $playbackUrl = null;

        if ($recording && ! $sendAudioFile) {
            $playbackUrl = app(RecordingUrlService::class)->resolve(
                $recording,
                $recording->source_url ?? $callLog?->recording_url,
            );
        }

        $call->update(['processing_status' => CallProcessingStatus::Transcribing]);

        $transcription = $transcriptionProvider->transcribe(new TranscriptionRequestData(
            callId: $callId,
            storagePath: $sendAudioFile ? $recording?->storage_path : null,
            storageDisk: $sendAudioFile ? $recording?->storage_disk : null,
            recordingUrl: $recording?->source_url ?? $callLog?->recording_url,
            mimeType: $recording?->mime_type,
            model: $requestedTranscriptionModel,
            fallbackModel: $modelKey,
            context: $context,
            sendAudioFile: $sendAudioFile,
            playbackUrl: $playbackUrl,
        ));

        if (! $transcription->success || ! is_array($transcription->data)) {
            $this->fail($config, $callId, $transcription->error ?? 'تبدیل گفتار به متن ناموفق بود.');
        }

        $transcript = trim((string) ($transcription->data['transcript'] ?? ''));

        if ($transcript === '') {
            $this->fail($config, $callId, 'متن مکالمه از فایل صوتی استخراج نشد.');
        }

        $this->storeTranscript($call, $transcript, $transcription->model, $transcription->durationMs, $transcription->data['speakers'] ?? null);

        $call->update(['processing_status' => CallProcessingStatus::Analyzing]);
        $tracker = app(CallProcessingTracker::class);
        $processingJob = $tracker->forCall($call->id);

        if ($processingJob) {
            $tracker->markSendingToAi($processingJob);
            $tracker->markWaitingForAi($processingJob);
        }

        $context = new PromptContextData(
            employeeName: $context->employeeName,
            department: $context->department,
            position: $context->position,
            callDirection: $context->callDirection,
            callDurationSeconds: $context->callDurationSeconds,
            customerNumber: $context->customerNumber,
            title: $context->title,
            customerName: $context->customerName,
            category: $context->category,
            notes: $context->notes,
            organizationName: $context->organizationName,
            organizationBusinessContext: $context->organizationBusinessContext,
            agentRole: $context->agentRole,
            transcript: $transcript,
        );

        $request = new AudioAnalysisRequestData(
            callId: $callId,
            model: $modelKey,
            promptVersion: $config->settings->promptVersion,
            context: $context,
            organizationId: $call->organization_id,
            organizationUserId: $call->organization_user_id,
            voipCallLogId: $call->voip_call_log_id,
            sendAudioFile: false,
        );

        $result = $provider->analyzeAudio($request);

        if (! $result->success || ! $result->data) {
            $this->fail($config, $callId, $result->error ?? 'Unknown analysis error');
        }

        $analysisData = $this->billing->buildAnalysisResult(
            response: $result->data,
            model: $llmModel,
            organizationId: $call->organization_id,
            organizationUserId: $call->organization_user_id,
            voipCallLogId: $call->voip_call_log_id,
            organizationLlmConnectionId: $config->connectionId ?: null,
            inputTokens: $result->inputTokens + $transcription->inputTokens,
            outputTokens: $result->outputTokens + $transcription->outputTokens,
            processingDurationMs: $result->durationMs + $transcription->durationMs,
            promptVersion: $config->settings->promptVersion,
            callId: $callId,
            source: $call->source ?? ConversationSource::Voip,
            transcript: $transcript,
            crmContext: array_filter([
                'current_user_name' => $employee?->full_name,
                'current_company_name' => $call->organization?->title,
            ], fn (mixed $value) => is_string($value) && trim($value) !== ''),
            // When the model finished — call day stays on calls.* / occurredAt().
            analyzedAt: now(),
        );

        $stored = $this->billing->storeAndCharge($analysisData, $this->analyses);

        event(new ConversationAnalyzed(
            organizationId: $call->organization_id,
            analysisId: $stored->id,
            result: $stored,
        ));

        return $stored;
    }

    private function fail(LlmConnectionConfig $config, int $callId, string $error): never
    {
        event(new AnalysisFailed(
            organizationId: $config->organizationId,
            callId: $callId,
            reason: $error,
        ));

        if (LlmTransientException::isTransientMessage($error)) {
            throw LlmTransientException::fromProviderError($error);
        }

        throw new \RuntimeException($error);
    }

    /** @param  list<array<string, mixed>>|null  $speakers */
    private function storeTranscript(
        Call $call,
        string $transcript,
        ?string $model,
        int $durationMs,
        ?array $speakers,
    ): void {
        $attributes = [
            'organization_id' => $call->organization_id,
            'voip_call_log_id' => $call->voip_call_log_id,
            'organization_user_id' => $call->organization_user_id,
            'content' => $transcript,
            'language' => 'fa',
            'duration_seconds' => $call->duration_seconds,
            'status' => CallTranscript::STATUS_COMPLETED,
            'error_message' => null,
            'processed_at' => now(),
            'transcription_provider' => 'llm',
            'model_name' => $model,
            'speakers_json' => $speakers,
            'processing_duration_ms' => $durationMs,
        ];

        $existing = CallTranscript::query()->where('call_id', $call->id)->latest('id')->first();

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        CallTranscript::query()->create([
            'call_id' => $call->id,
            ...$attributes,
        ]);
    }

    private function resolveCallDirection(Call $call, ?VoipCallLog $callLog): ?string
    {
        $fromLog = $callLog?->direction?->value;
        if (is_string($fromLog) && $fromLog !== '') {
            return $fromLog;
        }

        $fromCall = $call->direction;
        if (is_string($fromCall) && $fromCall !== '') {
            return $fromCall;
        }

        return null;
    }
}
