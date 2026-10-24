<?php

namespace App\Application\Llm\Services;

use App\Application\Llm\Prompts\TranscriptionPrompt;
use App\Domain\Llm\Contracts\CompletesJsonPrompt;
use App\Domain\Llm\Contracts\LlmProviderInterface;
use App\Domain\Llm\DTOs\PromptContextData;

/**
 * Whisper, including Groq whisper-large-v3, returns one stream of words and
 * cannot tell the agent from the customer. This labels that text before analysis.
 */
class SpeakerTurnLabeler
{
    /**
     * @return array{transcript: string, inputTokens: int, outputTokens: int, cachedTokens: int, durationMs: int}
     */
    public function label(LlmProviderInterface $provider, string $model, PromptContextData $context, string $transcript): array
    {
        $transcript = trim($transcript);
        $unchanged = [
            'transcript' => $transcript,
            'inputTokens' => 0,
            'outputTokens' => 0,
            'cachedTokens' => 0,
            'durationMs' => 0,
        ];

        if ($transcript === '' || ! TranscriptionPrompt::needsSpeakerLabels($transcript) || ! $provider instanceof CompletesJsonPrompt) {
            return $unchanged;
        }

        $result = $provider->completeJson(
            model: $model,
            system: TranscriptionPrompt::speakerTurnSystem(),
            user: TranscriptionPrompt::speakerTurnUser($context, $transcript),
            maxOutputTokens: min(8192, max(512, (int) ceil(mb_strlen($transcript) * 1.4) + 64)),
        );

        if (! $result->success || ! is_array($result->data)) {
            return $unchanged;
        }

        $labeled = trim((string) ($result->data['transcript'] ?? ''));
        if ($labeled === '' || TranscriptionPrompt::needsSpeakerLabels($labeled) || $this->droppedTooMuch($transcript, $labeled)) {
            return $unchanged;
        }

        return [
            'transcript' => $labeled,
            'inputTokens' => $result->inputTokens,
            'outputTokens' => $result->outputTokens,
            'cachedTokens' => $result->cachedTokens,
            'durationMs' => $result->durationMs,
        ];
    }

    private function droppedTooMuch(string $source, string $labeled): bool
    {
        $sourceLength = mb_strlen($this->wordsOnly($source));
        if ($sourceLength === 0) {
            return false;
        }

        return mb_strlen($this->wordsOnly($labeled)) < (int) floor($sourceLength * 0.6);
    }

    private function wordsOnly(string $transcript): string
    {
        $withoutLabels = preg_replace('/^(?:کارشناس|مشتری)\s*[:：]\s*/mu', '', $transcript) ?? $transcript;
        $compact = preg_replace('/\s+/u', '', $withoutLabels);

        return is_string($compact) ? $compact : '';
    }
}
