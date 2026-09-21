<?php

namespace App\Application\Llm;

use App\Application\Llm\Services\AudioAnalyzer;
use App\Application\Llm\Services\LlmConnectionResolver;
use App\Domain\Llm\Contracts\LlmLogRepositoryInterface;
use App\Domain\Llm\Contracts\LlmProviderInterface;
use App\Domain\Llm\DTOs\AnalysisResultData;
use App\Domain\Llm\DTOs\LlmConnectionConfig;
use App\Domain\Llm\Enums\LlmLogStatus;
use App\Domain\Llm\Enums\LlmOperation;
use App\Domain\Llm\Exceptions\LlmConnectionNotFoundException;
use App\Domain\Llm\ValueObjects\LlmOperationResult;
use App\Infrastructure\Llm\LlmOutboundGuard;

class AnalysisManager
{
    private ?int $organizationId = null;

    private ?int $connectionId = null;

    public function __construct(
        private LlmConnectionResolver $resolver,
        private LlmLogRepositoryInterface $logs,
        private AudioAnalyzer $audioAnalyzer,
    ) {}

    public static function forOrganization(int $organizationId): self
    {
        $instance = new self(
            resolver: app(LlmConnectionResolver::class),
            logs: app(LlmLogRepositoryInterface::class),
            audioAnalyzer: app(AudioAnalyzer::class),
        );
        $instance->organizationId = $organizationId;

        return $instance;
    }

    public function connection(?int $connectionId = null): self
    {
        $this->connectionId = $connectionId;

        return $this;
    }

    public function default(): self
    {
        $this->connectionId = null;

        return $this;
    }

    public function testConnection(): LlmOperationResult
    {
        $guard = app(LlmOutboundGuard::class);

        if (! $guard->remoteAnalysisEnabled()) {
            return LlmOperationResult::failure($guard->disabledMessage());
        }

        return $this->execute(LlmOperation::TestConnection, fn (LlmProviderInterface $provider) => $provider->testConnection());
    }

    public function analyzeCall(int $callId, ?string $model = null): AnalysisResultData
    {
        [$config, $provider] = $this->resolveContext();

        $result = $this->audioAnalyzer->analyze(
            callId: $callId,
            config: $config,
            provider: $provider,
            model: $model,
        );

        $this->logs->logOperation(
            connectionId: $config->connectionId,
            operation: LlmOperation::AnalyzeAudio,
            status: LlmLogStatus::Success,
            request: ['call_id' => $callId],
            response: ['score' => $result->score, 'sentiment' => $result->sentiment->value],
            inputTokens: $result->inputTokens,
            outputTokens: $result->outputTokens,
            cost: $result->cost,
            durationMs: $result->processingDurationMs,
            message: 'Audio analyzed successfully.',
        );

        return $result;
    }

    /** @return array{0: LlmConnectionConfig, 1: LlmProviderInterface} */
    private function resolveContext(): array
    {
        if ($this->organizationId === null) {
            throw new LlmConnectionNotFoundException('Organization context is required.');
        }

        return $this->resolver->resolve($this->organizationId, $this->connectionId);
    }

    private function execute(
        LlmOperation $operation,
        callable $callback,
        ?array $request = null,
    ): LlmOperationResult {
        [$config, $provider] = $this->resolveContext();

        if (! $config->isActive && $operation !== LlmOperation::TestConnection) {
            return LlmOperationResult::failure('LLM connection is inactive.');
        }

        $result = $callback($provider);

        $this->logs->logOperation(
            connectionId: $config->connectionId,
            operation: $operation,
            status: $result->status(),
            request: $request,
            response: $result->data,
            message: $result->message ?? $result->error,
            inputTokens: $result->inputTokens ?: null,
            outputTokens: $result->outputTokens ?: null,
            cost: $result->cost ?: null,
            durationMs: $result->durationMs ?: null,
        );

        return $result;
    }
}
