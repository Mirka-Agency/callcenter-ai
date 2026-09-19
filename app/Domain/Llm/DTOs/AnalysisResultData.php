<?php

namespace App\Domain\Llm\DTOs;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;

readonly class AnalysisResultData
{
    public function __construct(
        public int $organizationId,
        public ?int $organizationUserId,
        public ?int $voipCallLogId,
        public ?int $organizationLlmConnectionId,
        public string $llmProvider,
        public string $modelName,
        public int $score,
        public string $summary,
        public AnalysisSentiment $sentiment,
        public ?string $overallEvaluation,
        public array $strengths,
        public array $weaknesses,
        public array $nextActions,
        public int $inputTokens,
        public int $outputTokens,
        public float $cost,
        public int $processingDurationMs,
        public ?string $promptVersion = null,
        public ?int $id = null,
        public ?\DateTimeInterface $analyzedAt = null,
        public ?int $callId = null,
        public ConversationSource $source = ConversationSource::Voip,
        public ?string $transcript = null,
        public array $performanceDimensions = [],
        public array $customerInsights = [],
        public array $operationalInsights = [],
        public array $leadQuality = [],
        public array $concerns = [],
        public array $customerIdentity = [],
        public ?int $llmModelId = null,
        public ?float $inputPriceSnapshot = null,
        public ?float $outputPriceSnapshot = null,
        public ?float $cachedInputPriceSnapshot = null,
        public ?float $reasoningPriceSnapshot = null,
        public bool $isEvaluable = true,
        public bool $needsAttention = false,
        public array $attention = [],
    ) {}

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    public function withId(int $id): self
    {
        return new self(
            organizationId: $this->organizationId,
            organizationUserId: $this->organizationUserId,
            voipCallLogId: $this->voipCallLogId,
            organizationLlmConnectionId: $this->organizationLlmConnectionId,
            llmProvider: $this->llmProvider,
            modelName: $this->modelName,
            score: $this->score,
            summary: $this->summary,
            sentiment: $this->sentiment,
            overallEvaluation: $this->overallEvaluation,
            strengths: $this->strengths,
            weaknesses: $this->weaknesses,
            nextActions: $this->nextActions,
            inputTokens: $this->inputTokens,
            outputTokens: $this->outputTokens,
            cost: $this->cost,
            processingDurationMs: $this->processingDurationMs,
            promptVersion: $this->promptVersion,
            id: $id,
            analyzedAt: $this->analyzedAt,
            callId: $this->callId,
            source: $this->source,
            transcript: $this->transcript,
            performanceDimensions: $this->performanceDimensions,
            customerInsights: $this->customerInsights,
            operationalInsights: $this->operationalInsights,
            leadQuality: $this->leadQuality,
            concerns: $this->concerns,
            customerIdentity: $this->customerIdentity,
            llmModelId: $this->llmModelId,
            inputPriceSnapshot: $this->inputPriceSnapshot,
            outputPriceSnapshot: $this->outputPriceSnapshot,
            cachedInputPriceSnapshot: $this->cachedInputPriceSnapshot,
            reasoningPriceSnapshot: $this->reasoningPriceSnapshot,
            isEvaluable: $this->isEvaluable,
            needsAttention: $this->needsAttention,
            attention: $this->attention,
        );
    }

    public static function fromProviderResponse(
        array $response,
        int $organizationId,
        ?int $organizationUserId,
        ?int $voipCallLogId,
        ?int $organizationLlmConnectionId,
        string $llmProvider,
        string $modelName,
        int $inputTokens,
        int $outputTokens,
        float $cost,
        int $processingDurationMs,
        ?string $promptVersion = null,
        ?int $callId = null,
        ConversationSource $source = ConversationSource::Voip,
        ?string $transcript = null,
        ?int $llmModelId = null,
        ?float $inputPriceSnapshot = null,
        ?float $outputPriceSnapshot = null,
        ?float $cachedInputPriceSnapshot = null,
        ?float $reasoningPriceSnapshot = null,
    ): self {
        $performance = (array) ($response['performance_dimensions'] ?? $response['performance'] ?? []);
        $customer = (array) ($response['customer_insights'] ?? $response['customer'] ?? []);
        $operational = (array) ($response['operational_insights'] ?? $response['operational'] ?? []);
        $leadQuality = (array) ($response['lead_quality'] ?? []);
        $concerns = (array) ($response['concerns'] ?? []);
        $customerIdentity = (array) ($response['customer_identity'] ?? []);
        $attention = (array) ($response['needs_attention'] ?? []);
        $score = (int) ($response['score'] ?? $performance['overall_score'] ?? 0);
        $isEvaluable = (bool) ($response['evaluable'] ?? true) && $score > 0;

        return new self(
            organizationId: $organizationId,
            organizationUserId: $organizationUserId,
            voipCallLogId: $voipCallLogId,
            organizationLlmConnectionId: $organizationLlmConnectionId,
            llmProvider: $llmProvider,
            modelName: $modelName,
            score: $score,
            summary: (string) ($response['summary'] ?? ''),
            sentiment: AnalysisSentiment::fromAnalysisValue($response['sentiment'] ?? $customer['sentiment'] ?? ''),
            overallEvaluation: $response['overall_evaluation'] ?? $response['evaluation'] ?? null,
            strengths: (array) ($response['strengths'] ?? []),
            weaknesses: (array) ($response['weaknesses'] ?? []),
            nextActions: (array) ($response['next_actions'] ?? $response['recommended_improvements'] ?? []),
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $cost,
            processingDurationMs: $processingDurationMs,
            promptVersion: $promptVersion,
            analyzedAt: now(),
            callId: $callId,
            source: $source,
            transcript: $transcript,
            performanceDimensions: $performance,
            customerInsights: $customer,
            operationalInsights: $operational,
            leadQuality: $leadQuality,
            concerns: $concerns,
            customerIdentity: $customerIdentity,
            llmModelId: $llmModelId,
            inputPriceSnapshot: $inputPriceSnapshot,
            outputPriceSnapshot: $outputPriceSnapshot,
            cachedInputPriceSnapshot: $cachedInputPriceSnapshot,
            reasoningPriceSnapshot: $reasoningPriceSnapshot,
            isEvaluable: $isEvaluable,
            needsAttention: (bool) ($attention['needed'] ?? false),
            attention: $attention,
        );
    }
}
