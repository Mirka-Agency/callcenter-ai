<?php

namespace App\Services;

use App\Application\Llm\Services\AiCostCalculator;
use App\Application\Llm\Services\AnalysisResponseNormalizer;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Contracts\ConversationAnalysisRepositoryInterface;
use App\Domain\Llm\DTOs\AnalysisResultData;
use App\Models\ConversationAnalysis;
use App\Models\LlmModel;
use App\Support\OnPrem;
use Illuminate\Support\Facades\DB;

class AiBillingService
{
    public function __construct(
        private WalletService $wallets,
        private AiCostCalculator $costCalculator,
        private LlmModelResolver $modelResolver,
    ) {}

    public function assertCanAnalyze(int $organizationId): void
    {
        if (OnPrem::billingHidden()) {
            return;
        }

        $this->wallets->assertSufficientBalance($organizationId);
    }

    public function resolveModel(int $organizationId): LlmModel
    {
        return $this->modelResolver->resolveForOrganization($organizationId);
    }

    public function buildAnalysisResult(
        array $response,
        LlmModel $model,
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
    ): AnalysisResultData {
        $response = app(AnalysisResponseNormalizer::class)->apply(
            app(WeaknessEvaluationFilter::class)->filterResponse($response),
            $crmContext,
        );

        $pricing = $this->costCalculator->calculateWithSnapshots($model, $inputTokens, $outputTokens);

        return AnalysisResultData::fromProviderResponse(
            response: $response,
            organizationId: $organizationId,
            organizationUserId: $organizationUserId,
            voipCallLogId: $voipCallLogId,
            organizationLlmConnectionId: $organizationLlmConnectionId,
            llmProvider: $model->provider?->code ?? 'unknown',
            modelName: $model->model_key,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $pricing['cost'],
            processingDurationMs: $processingDurationMs,
            promptVersion: $promptVersion,
            callId: $callId,
            source: $source,
            transcript: $transcript,
            llmModelId: $model->id,
            inputPriceSnapshot: $pricing['input_price'],
            outputPriceSnapshot: $pricing['output_price'],
            cachedInputPriceSnapshot: $pricing['cached_price'],
            reasoningPriceSnapshot: $pricing['reasoning_price'],
        );
    }

    public function storeAndCharge(AnalysisResultData $data, ConversationAnalysisRepositoryInterface $repository): AnalysisResultData
    {
        return DB::transaction(function () use ($data, $repository) {
            $analysisId = $repository->store($data);
            $analysis = ConversationAnalysis::query()->findOrFail($analysisId);

            if (! OnPrem::billingHidden()) {
                $this->wallets->chargeForAnalysis($analysis, (float) $analysis->cost);
            }

            return $data->withId($analysisId);
        });
    }

    public function walletOverview(int $organizationId): array
    {
        $wallet = $this->wallets->forOrganization($organizationId);
        $model = $this->modelResolver->overviewForOrganization($organizationId);
        $usage = app(AiUsageAnalyticsService::class)->organizationOverview(
            $organizationId,
            now()->startOfMonth(),
            now()->endOfMonth(),
        );

        $lastAnalysisAt = ConversationAnalysis::query()
            ->where('organization_id', $organizationId)
            ->max('analyzed_at');

        return [
            'balance' => (float) $wallet->balance,
            'currency' => $wallet->currency,
            'low_balance_threshold' => $wallet->lowBalanceThreshold(),
            'model' => $model,
            'month_tokens' => $usage['total_tokens'] ?? 0,
            'month_cost' => $usage['total_cost'] ?? 0,
            'month_analyses' => $usage['analyses_count'] ?? 0,
            'last_analysis_at' => $lastAnalysisAt,
        ];
    }
}
