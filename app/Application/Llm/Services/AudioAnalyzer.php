<?php

namespace App\Application\Llm\Services;

use App\Application\Transcription\Services\CallTranscriber;
use App\Application\Transcription\Services\SpeakerRoleMapper;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Contracts\ConversationAnalysisRepositoryInterface;
use App\Domain\Llm\Contracts\LlmProviderInterface;
use App\Domain\Llm\DTOs\AnalysisResultData;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\DTOs\LlmConnectionConfig;
use App\Domain\Llm\DTOs\PromptContextData;
use App\Domain\Llm\Events\AnalysisFailed;
use App\Domain\Llm\Events\ConversationAnalyzed;
use App\Domain\Llm\Exceptions\LlmTransientException;
use App\Domain\Llm\ValueObjects\LlmOperationResult;
use App\Infrastructure\Llm\LlmOutboundGuard;
use App\Models\Call;
use App\Models\OrganizationUser;
use App\Models\VoipCallLog;
use App\Services\CallProcessingTracker;
use App\Services\WeaknessEvaluationFilter;

class AudioAnalyzer
{
    public function __construct(
        private ConversationAnalysisRepositoryInterface $analyses,
        private CallTranscriber $transcriber,
        private SpeakerRoleMapper $speakerRoles,
    ) {}

    public function analyze(
        int $callId,
        LlmConnectionConfig $config,
        LlmProviderInterface $provider,
        ?string $model = null,
    ): AnalysisResultData {
        app(LlmOutboundGuard::class)->assertRemoteAnalysisEnabled();

        $call = Call::query()->with(['recording', 'organization'])->findOrFail($callId);

        $modelKey = $model ?? $config->settings->defaultModel;
        if (! $modelKey) {
            throw new \RuntimeException('Missing LLM_MODEL environment configuration.');
        }

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

        $storedTranscript = $this->transcriber->transcribe($call);
        $transcript = trim($storedTranscript->content);
        $transcriptionInput = 0;
        $transcriptionOutput = 0;
        $transcriptionDuration = (int) ($storedTranscript->processing_duration_ms ?? 0);

        if ($storedTranscript->segments_json) {
            $mapping = $this->speakerRoles->map($storedTranscript, $provider, $modelKey, $context);
            $transcript = $storedTranscript->content;
            $transcriptionInput = $mapping?->inputTokens ?? 0;
            $transcriptionOutput = $mapping?->outputTokens ?? 0;
            $transcriptionDuration += $mapping?->durationMs ?? 0;
        } else {
            $turns = app(SpeakerTurnLabeler::class)->label($provider, $modelKey, $context, $transcript);
            $transcript = $turns['transcript'];
            $transcriptionInput = $turns['inputTokens'];
            $transcriptionOutput = $turns['outputTokens'];
            $transcriptionDuration += $turns['durationMs'];
            if ($storedTranscript->content !== $transcript) {
                $storedTranscript->update(['content' => $transcript]);
            }
        }

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

        $triage = app(TranscriptTriage::class)->decide($provider, $modelKey, $context);

        if ($triage->skipFullAnalysis) {
            $result = LlmOperationResult::success(
                data: $triage->payload,
                inputTokens: $triage->inputTokens,
                outputTokens: $triage->outputTokens,
                durationMs: $triage->durationMs,
                model: $modelKey,
                cachedTokens: $triage->cachedTokens,
            );
        } else {
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
        }

        if (! is_array($result->data)) {
            $this->fail($config, $callId, 'Unknown analysis error');
        }

        $triageInput = $triage->skipFullAnalysis ? 0 : $triage->inputTokens;
        $triageOutput = $triage->skipFullAnalysis ? 0 : $triage->outputTokens;
        $triageDuration = $triage->skipFullAnalysis ? 0 : $triage->durationMs;

        $analysisData = $this->analysisResult(
            response: $result->data,
            providerCode: $config->providerCode->value,
            modelName: $modelKey,
            organizationId: $call->organization_id,
            organizationUserId: $call->organization_user_id,
            voipCallLogId: $call->voip_call_log_id,
            organizationLlmConnectionId: $config->connectionId ?: null,
            inputTokens: $result->inputTokens + $transcriptionInput + $triageInput,
            outputTokens: $result->outputTokens + $transcriptionOutput + $triageOutput,
            processingDurationMs: $result->durationMs + $transcriptionDuration + $triageDuration,
            promptVersion: $config->settings->promptVersion,
            callId: $callId,
            source: $call->source ?? ConversationSource::Voip,
            transcript: $transcript,
            crmContext: array_filter([
                'current_user_name' => $employee?->full_name,
                'current_company_name' => $call->organization?->title,
            ], fn (mixed $value) => is_string($value) && trim($value) !== ''),
            analyzedAt: now(),
        );

        $stored = $analysisData->withId($this->analyses->store($analysisData));

        event(new ConversationAnalyzed(
            organizationId: $call->organization_id,
            analysisId: $stored->id,
            result: $stored,
        ));

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>|null  $crmContext
     */
    private function analysisResult(
        array $response,
        string $providerCode,
        string $modelName,
        int $organizationId,
        ?int $organizationUserId,
        ?int $voipCallLogId,
        ?int $organizationLlmConnectionId,
        int $inputTokens,
        int $outputTokens,
        int $processingDurationMs,
        ?string $promptVersion = null,
        ?int $callId = null,
        ?ConversationSource $source = null,
        ?string $transcript = null,
        ?array $crmContext = null,
        ?\DateTimeInterface $analyzedAt = null,
    ): AnalysisResultData {
        $response = app(AnalysisResponseNormalizer::class)->apply(
            app(WeaknessEvaluationFilter::class)->filterResponse($response),
            $crmContext,
        );

        return AnalysisResultData::fromProviderResponse(
            response: $response,
            organizationId: $organizationId,
            organizationUserId: $organizationUserId,
            voipCallLogId: $voipCallLogId,
            organizationLlmConnectionId: $organizationLlmConnectionId,
            llmProvider: $providerCode,
            modelName: $modelName,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            processingDurationMs: $processingDurationMs,
            promptVersion: $promptVersion,
            callId: $callId,
            source: $source ?? ConversationSource::Voip,
            transcript: $transcript,
            llmModelId: null,
            analyzedAt: $analyzedAt,
        );
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
