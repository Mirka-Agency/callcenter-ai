<?php

namespace App\Application\Llm\Services;

use App\Domain\Llm\Contracts\CompletesJsonPrompt;
use App\Domain\Llm\Contracts\LlmProviderInterface;
use App\Domain\Llm\DTOs\PromptContextData;

/**
 * A short classification before the full analysis prompt.
 * Uncertain results keep the full analysis so a real conversation is never dropped.
 */
class TranscriptTriage
{
    private const FULL_ANALYSIS_CHARS = 900;

    public function decide(LlmProviderInterface $provider, string $model, PromptContextData $context): TranscriptTriageDecision
    {
        $transcript = trim((string) $context->transcript);

        if ($transcript === '' || ! $provider instanceof CompletesJsonPrompt) {
            return TranscriptTriageDecision::full();
        }

        // A real conversation is long enough that a second full-transcript call
        // only adds latency and tokens. Short calls still get this cheap check
        // so silence and one-line personal calls can skip the analysis prompt.
        if (mb_strlen($transcript) >= self::FULL_ANALYSIS_CHARS) {
            return TranscriptTriageDecision::full();
        }

        $result = $provider->completeJson(
            model: $model,
            system: $this->systemPrompt(),
            user: $this->userPrompt($context, $transcript),
            maxOutputTokens: 512,
        );

        if (! $result->success || ! is_array($result->data)) {
            return TranscriptTriageDecision::full();
        }

        $kind = $result->data['kind'] ?? null;
        $confidence = (float) ($result->data['confidence'] ?? 0);
        $threshold = (float) config('llm.triage_confidence', 0.75);

        if (! in_array($kind, ['personal', 'none'], true) || $confidence < $threshold) {
            return TranscriptTriageDecision::full(
                inputTokens: $result->inputTokens,
                outputTokens: $result->outputTokens,
                cachedTokens: $result->cachedTokens,
                durationMs: $result->durationMs,
            );
        }

        return new TranscriptTriageDecision(
            skipFullAnalysis: true,
            payload: $this->payload($kind, $result->data),
            inputTokens: $result->inputTokens,
            outputTokens: $result->outputTokens,
            cachedTokens: $result->cachedTokens,
            durationMs: $result->durationMs,
        );
    }

    /** @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(string $kind, array $data): array
    {
        $personal = $kind === 'personal';
        $summary = trim((string) ($data['summary'] ?? ''));
        $reason = trim((string) ($data['personal_reason'] ?? ''));

        if ($summary === '') {
            $summary = $personal
                ? 'این تماس شخصی بود و به‌عنوان عملکرد کارشناس ارزیابی نشد.'
                : 'مکالمه قابل ارزیابی در این تماس شکل نگرفت.';
        }

        return [
            'score' => 0,
            'evaluable' => false,
            'is_personal' => $personal,
            'personal_reason' => $personal ? ($reason !== '' ? $reason : $summary) : '',
            'summary' => $summary,
            'sentiment' => 'neutral',
            'overall_evaluation' => '',
            'strengths' => [],
            'weaknesses' => [],
            'next_actions' => [],
            'customer_insights' => [
                'sentiment' => 'neutral',
                'intent' => '',
                'purchase_probability' => 0,
                'urgency_level' => 'low',
                'risk_level' => 'low',
            ],
            'lead_quality' => [
                'score' => 0,
                'level' => 'low',
                'reason' => '',
                'buying_intent_signals' => [],
            ],
            'concerns' => [],
            'needs_attention' => [
                'needed' => false,
                'categories' => [],
                'reason' => '',
            ],
            'customer_identity' => [
                'person_name' => '',
                'company_name' => '',
                'confidence' => 0,
                'evidence' => '',
            ],
            'coaching_analysis' => null,
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
تو فقط تماس را دسته‌بندی می‌کنی و فقط یک شیء JSON برمی‌گردانی.
- kind فقط یکی از real ، personal ، none
- confidence عدد اعشاری صفر تا یک
- summary برای personal و none یک یا دو جمله فارسی است. برای real رشته خالی بگذار
- personal_reason برای personal یک جمله کوتاه فارسی است. در غیر این صورت رشته خالی

real یعنی فروش، پشتیبانی، پیگیری پرداخت، یا هماهنگی کاری خود سازمان. اگر کارشناس از طرف سازمان پول می‌خواهد، real است.
personal یعنی کارشناس برای کار خودش تماس گرفته است: سفارش غذا، خرید شخصی، خانواده یا دوست.
none یعنی مکالمه شکل نگرفته: سکوت، بوق، موسیقی انتظار، یا سلام و قطع بدون موضوع.
نام کارشناس و نام سازمان زمینه، هویت مشتری نیست.

اگر تردید داری kind را real بگذار و confidence را پایین بگذار.
PROMPT;
    }

    private function userPrompt(PromptContextData $context, string $transcript): string
    {
        $lines = ['متن مکالمه را دسته‌بندی کن.'];
        $organization = trim((string) $context->organizationName);
        $business = trim((string) $context->organizationBusinessContext);
        $agent = trim((string) $context->employeeName);
        $direction = trim((string) $context->callDirection);

        if ($organization !== '') {
            $lines[] = "سازمان: {$organization}";
        }
        if ($business !== '') {
            $lines[] = "زمینه فعالیت سازمان: {$business}";
        }
        if ($agent !== '') {
            $lines[] = "کارشناس: {$agent}";
        }
        if ($direction !== '') {
            $lines[] = "جهت تماس: {$direction}";
        }

        $lines[] = "متن مکالمه:\n{$transcript}";

        return implode("\n", $lines);
    }
}
