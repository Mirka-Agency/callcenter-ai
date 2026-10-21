<?php

namespace App\Application\Llm\Services;

readonly class TranscriptTriageDecision
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public bool $skipFullAnalysis,
        public array $payload,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cachedTokens = 0,
        public int $durationMs = 0,
    ) {}

    public static function full(
        int $inputTokens = 0,
        int $outputTokens = 0,
        int $cachedTokens = 0,
        int $durationMs = 0,
    ): self {
        return new self(
            skipFullAnalysis: false,
            payload: [],
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cachedTokens: $cachedTokens,
            durationMs: $durationMs,
        );
    }
}
