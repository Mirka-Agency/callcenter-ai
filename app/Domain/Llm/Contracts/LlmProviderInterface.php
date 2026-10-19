<?php

namespace App\Domain\Llm\Contracts;

use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\DTOs\LlmConnectionConfig;
use App\Domain\Llm\DTOs\TranscriptionRequestData;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Domain\Llm\ValueObjects\LlmOperationResult;

interface LlmProviderInterface
{
    public function getProviderCode(): LlmProviderCode;

    public function configure(LlmConnectionConfig $config): void;

    public function testConnection(): LlmOperationResult;

    /** @return list<string> */
    public function supportedModels(): array;

    public function analyzeAudio(AudioAnalysisRequestData $request): LlmOperationResult;

    public function transcribe(TranscriptionRequestData $request): LlmOperationResult;
}
