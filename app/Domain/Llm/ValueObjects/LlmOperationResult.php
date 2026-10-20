<?php

namespace App\Domain\Llm\ValueObjects;

use App\Domain\Llm\Enums\LlmLogStatus;

readonly class LlmOperationResult
{
    public function __construct(
        public bool $success,
        public ?array $data = null,
        public ?string $message = null,
        public ?string $error = null,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public float $cost = 0.0,
        public int $durationMs = 0,
        public ?string $model = null,
        public int $cachedTokens = 0,
    ) {}

    public static function success(
        ?array $data = null,
        ?string $message = null,
        int $inputTokens = 0,
        int $outputTokens = 0,
        float $cost = 0.0,
        int $durationMs = 0,
        ?string $model = null,
        int $cachedTokens = 0,
    ): self {
        return new self(
            success: true,
            data: $data,
            message: $message,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $cost,
            durationMs: $durationMs,
            model: $model,
            cachedTokens: $cachedTokens,
        );
    }

    public static function failure(string $error, ?array $data = null): self
    {
        return new self(success: false, data: $data, error: $error);
    }

    public function status(): LlmLogStatus
    {
        return $this->success ? LlmLogStatus::Success : LlmLogStatus::Failed;
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
