<?php

namespace App\Application\Call\Services;

use App\Application\Intelligence\Jobs\SyncCrmJob;
use App\Application\Intelligence\Jobs\UpdateEmployeeMetricsJob;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Contracts\ConversationAnalysisRepositoryInterface;
use App\Domain\Llm\DTOs\AnalysisResultData;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Call;
use App\Services\CallProcessingTracker;
use App\Support\SampleConversationAnalysisCache;

class SampleConversationAnalysisService
{
    public function __construct(
        private ConversationAnalysisRepositoryInterface $analyses,
        private CallProcessingTracker $tracker,
    ) {}

    public function apply(int $callId, string $sampleId): void
    {
        $payload = SampleConversationAnalysisCache::get($sampleId);
        $call = Call::query()->findOrFail($callId);
        $job = $this->tracker->forCall($callId);

        if ($job) {
            $this->tracker->markProcessingStarted($job);
            $this->tracker->markSendingToAi($job);
            $this->tracker->markWaitingForAi($job);
            $this->tracker->markProcessingResult($job);
        }

        $call->update([
            'processing_status' => CallProcessingStatus::Analyzing,
            'processing_error' => null,
        ]);

        $analysis = $this->buildResultData($call, $payload);
        $analysisId = $this->analyses->store($analysis);

        $call->update(['processing_status' => CallProcessingStatus::Analyzed]);

        if ($job) {
            $this->tracker->markCompleted($job);
        }

        UpdateEmployeeMetricsJob::dispatch($callId);
        SyncCrmJob::dispatch($callId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildResultData(Call $call, array $payload): AnalysisResultData
    {
        $inputTokens = (int) ($payload['input_tokens'] ?? 0);
        $outputTokens = (int) ($payload['output_tokens'] ?? 0);

        return new AnalysisResultData(
            organizationId: $call->organization_id,
            organizationUserId: $call->organization_user_id,
            voipCallLogId: $call->voip_call_log_id,
            organizationLlmConnectionId: null,
            llmProvider: 'sample-cache',
            modelName: 'sample-cache',
            score: (int) ($payload['score'] ?? 0),
            summary: (string) ($payload['summary'] ?? ''),
            sentiment: AnalysisSentiment::tryFrom((string) ($payload['sentiment'] ?? '')) ?? AnalysisSentiment::Neutral,
            overallEvaluation: $payload['overall_evaluation'] ?? null,
            strengths: (array) ($payload['strengths'] ?? []),
            weaknesses: (array) ($payload['weaknesses'] ?? []),
            nextActions: (array) ($payload['next_actions'] ?? []),
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: (float) ($payload['cost'] ?? 0),
            processingDurationMs: (int) ($payload['processing_duration_ms'] ?? 0),
            promptVersion: 'sample-cache-v1',
            analyzedAt: now(),
            callId: $call->id,
            source: ConversationSource::ManualUpload,
            transcript: null,
            performanceDimensions: (array) ($payload['performance_dimensions'] ?? []),
            customerInsights: (array) ($payload['customer_insights'] ?? []),
            operationalInsights: (array) ($payload['operational_insights'] ?? []),
            leadQuality: (array) ($payload['lead_quality'] ?? []),
            concerns: (array) ($payload['concerns'] ?? []),
            customerIdentity: (array) ($payload['customer_identity'] ?? []),
            llmModelId: null,
            inputPriceSnapshot: null,
            outputPriceSnapshot: null,
            cachedInputPriceSnapshot: null,
            reasoningPriceSnapshot: null,
        );
    }
}
