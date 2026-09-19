<?php

namespace App\Infrastructure\Llm\Repositories;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Contracts\ConversationAnalysisRepositoryInterface;
use App\Domain\Llm\DTOs\AnalysisResultData;
use App\Models\ConversationAnalysis;

class EloquentConversationAnalysisRepository implements ConversationAnalysisRepositoryInterface
{
    public function store(AnalysisResultData $data): int
    {
        $attributes = [
            'organization_id' => $data->organizationId,
            'organization_user_id' => $data->organizationUserId,
            'voip_call_log_id' => $data->voipCallLogId,
            'llm_provider' => $data->llmProvider,
            'model_name' => $data->modelName,
            'prompt_version' => $data->promptVersion,
            'score' => $data->score,
            'is_evaluable' => $data->isEvaluable,
            'needs_attention' => $data->needsAttention,
            'attention_json' => $data->attention ?: null,
            'summary' => $data->summary,
            'transcript' => $data->transcript,
            'sentiment' => $data->sentiment,
            'overall_evaluation' => $data->overallEvaluation,
            'strengths_json' => $data->strengths,
            'weaknesses_json' => $data->weaknesses,
            'next_actions_json' => $data->nextActions,
            'call_id' => $data->callId,
            'source' => $data->source,
            'performance_dimensions_json' => $data->performanceDimensions ?: null,
            'customer_insights_json' => $data->customerInsights ?: null,
            'operational_insights_json' => $data->operationalInsights ?: null,
            'lead_quality_json' => $data->leadQuality ?: null,
            'concerns_json' => $data->concerns ?: null,
            'customer_identity_json' => $data->customerIdentity ?: null,
            'input_tokens' => $data->inputTokens,
            'output_tokens' => $data->outputTokens,
            'total_tokens' => $data->totalTokens(),
            'cost' => $data->cost,
            'llm_model_id' => $data->llmModelId,
            'input_price_snapshot' => $data->inputPriceSnapshot,
            'output_price_snapshot' => $data->outputPriceSnapshot,
            'cached_input_price_snapshot' => $data->cachedInputPriceSnapshot,
            'reasoning_price_snapshot' => $data->reasoningPriceSnapshot,
            'processing_duration_ms' => $data->processingDurationMs,
            'analyzed_at' => $data->analyzedAt ?? now(),
        ];

        $existing = $data->callId
            ? ConversationAnalysis::query()
                ->where('call_id', $data->callId)
                ->latest('analyzed_at')
                ->first()
            : null;

        if ($existing) {
            $existing->update($attributes);

            return $existing->id;
        }

        return ConversationAnalysis::query()->create($attributes)->id;
    }

    public function findLatestForCall(int $callId): ?AnalysisResultData
    {
        $analysis = ConversationAnalysis::query()
            ->where('call_id', $callId)
            ->latest('analyzed_at')
            ->first();

        return $analysis ? $this->toDto($analysis) : null;
    }

    private function toDto(ConversationAnalysis $analysis): AnalysisResultData
    {
        return new AnalysisResultData(
            organizationId: $analysis->organization_id,
            organizationUserId: $analysis->organization_user_id,
            voipCallLogId: $analysis->voip_call_log_id,
            organizationLlmConnectionId: null,
            llmProvider: $analysis->llm_provider,
            modelName: $analysis->model_name,
            score: $analysis->score,
            summary: $analysis->summary,
            sentiment: $analysis->sentiment,
            overallEvaluation: $analysis->overall_evaluation,
            strengths: $analysis->strengths_json ?? [],
            weaknesses: $analysis->weaknesses_json ?? [],
            nextActions: $analysis->next_actions_json ?? [],
            inputTokens: $analysis->input_tokens,
            outputTokens: $analysis->output_tokens,
            cost: (float) $analysis->cost,
            processingDurationMs: $analysis->processing_duration_ms,
            promptVersion: $analysis->prompt_version,
            id: $analysis->id,
            analyzedAt: $analysis->analyzed_at,
            callId: $analysis->call_id,
            source: $analysis->source ?? ConversationSource::Voip,
            transcript: $analysis->transcript,
            performanceDimensions: $analysis->performance_dimensions_json ?? [],
            customerInsights: $analysis->customer_insights_json ?? [],
            operationalInsights: $analysis->operational_insights_json ?? [],
            leadQuality: $analysis->lead_quality_json ?? [],
            concerns: $analysis->concerns_json ?? [],
            customerIdentity: $analysis->customer_identity_json ?? [],
            llmModelId: $analysis->llm_model_id,
            inputPriceSnapshot: $analysis->input_price_snapshot !== null ? (float) $analysis->input_price_snapshot : null,
            outputPriceSnapshot: $analysis->output_price_snapshot !== null ? (float) $analysis->output_price_snapshot : null,
            cachedInputPriceSnapshot: $analysis->cached_input_price_snapshot !== null ? (float) $analysis->cached_input_price_snapshot : null,
            reasoningPriceSnapshot: $analysis->reasoning_price_snapshot !== null ? (float) $analysis->reasoning_price_snapshot : null,
            isEvaluable: $analysis->isEvaluable(),
            needsAttention: (bool) $analysis->needs_attention,
            attention: $analysis->attention_json ?? [],
        );
    }
}
