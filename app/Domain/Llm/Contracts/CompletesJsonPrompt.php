<?php

namespace App\Domain\Llm\Contracts;

use App\Domain\Llm\ValueObjects\LlmOperationResult;

interface CompletesJsonPrompt
{
    public function completeJson(string $model, string $system, string $user, int $maxOutputTokens = 512): LlmOperationResult;
}
