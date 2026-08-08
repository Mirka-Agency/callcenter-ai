<?php

namespace App\Application\Llm\Services;

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
use App\Models\Call;
use App\Models\OrganizationUser;
use App\Models\VoipCallLog;
use App\Services\AiBillingService;
use App\Services\RecordingUrlService;

class AudioAnalyzer
{
    public function __construct(
        private ConversationAnalysisRepositoryInterface $analyses,
        private AiBillingService $billing,
    ) {}

    public function analyze(
        int $callId,
        LlmConnectionConfig $config,
        LlmProviderInterface $provider,
        ?string $model = null,
    ): AnalysisResultData {
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

        $customerNumber = $callLog?->direction?->value === 'inbound'
            ? $callLog->source_number
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
            callDirection: $callLog?->direction?->value ?? ($call->isManualUpload() ? 'manual' : null),
            callDurationSeconds: $callDuration,
            customerNumber: $customerNumber,
            title: $call->title,
            customerName: $call->customer_name,
            category: $call->category,
            notes: $call->notes,
            organizationName: $call->organization?->title,
            organizationBusinessContext: $call->organization?->business_context,
        );

        $recording = $call->recording;
        $sendAudioFile = $llmModel->sends_audio_file;
        $playbackUrl = null;

        if ($recording && ! $sendAudioFile) {
            $playbackUrl = app(RecordingUrlService::class)->resolve(
                $recording,
                $recording->source_url ?? $callLog?->recording_url,
            );
        }

        $request = new AudioAnalysisRequestData(
            callId: $callId,
            storagePath: $sendAudioFile ? $recording?->storage_path : null,
            storageDisk: $sendAudioFile ? $recording?->storage_disk : null,
            recordingUrl: $recording?->source_url ?? $callLog?->recording_url,
            mimeType: $recording?->mime_type,
            model: $modelKey,
            promptVersion: $config->settings->promptVersion,
            context: $context,
            organizationId: $call->organization_id,
            organizationUserId: $call->organization_user_id,
            voipCallLogId: $call->voip_call_log_id,
            sendAudioFile: $sendAudioFile,
            playbackUrl: $playbackUrl,
        );

        $result = $provider->analyzeAudio($request);

        if (! $result->success || ! $result->data) {
            $error = $result->error ?? 'Unknown analysis error';

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

        $analysisData = $this->billing->buildAnalysisResult(
            response: $result->data,
            model: $llmModel,
            organizationId: $call->organization_id,
            organizationUserId: $call->organization_user_id,
            voipCallLogId: $call->voip_call_log_id,
            organizationLlmConnectionId: $config->connectionId ?: null,
            inputTokens: $result->inputTokens,
            outputTokens: $result->outputTokens,
            processingDurationMs: $result->durationMs,
            promptVersion: $config->settings->promptVersion,
            callId: $callId,
            source: $call->source ?? ConversationSource::Voip,
            transcript: $result->data['transcript'] ?? null,
            crmContext: array_filter([
                'current_user_name' => $employee?->full_name,
                'current_company_name' => $call->organization?->title,
            ], fn (mixed $value) => is_string($value) && trim($value) !== ''),
        );

        $stored = $this->billing->storeAndCharge($analysisData, $this->analyses);

        event(new ConversationAnalyzed(
            organizationId: $call->organization_id,
            analysisId: $stored->id,
            result: $stored,
        ));

        return $stored;
    }
}
