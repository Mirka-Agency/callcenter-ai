<?php

namespace App\Infrastructure\Llm\Adapters;

use App\Domain\Llm\Contracts\LlmProviderInterface;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\DTOs\LlmConnectionConfig;
use App\Domain\Llm\ValueObjects\LlmOperationResult;

abstract class AbstractLlmProvider implements LlmProviderInterface
{
    protected LlmConnectionConfig $config;

    public function configure(LlmConnectionConfig $config): void
    {
        $this->config = $config;
    }

    protected function hasApiKey(): bool
    {
        return filled($this->config->credentials->apiKey);
    }

    protected function resolveModel(?string $model, string $fallback): string
    {
        return $model
            ?? $this->config->settings->defaultModel
            ?? $fallback;
    }

    protected function parseJsonResponse(string $content): ?array
    {
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $decoded = json_decode($matches[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    protected function failure(string $error, ?array $data = null): LlmOperationResult
    {
        return LlmOperationResult::failure($error, $data);
    }

    protected function hasRealAudio(AudioAnalysisRequestData $request): bool
    {
        return filled($request->storagePath) || filled($request->recordingUrl);
    }

    protected function refuseDemoAnalysis(AudioAnalysisRequestData $request, string $model): ?LlmOperationResult
    {
        if (! $this->hasRealAudio($request)) {
            return null;
        }

        if (! $this->hasApiKey()) {
            return $this->failure('برای تحلیل واقعی تماس، کلید API هوش مصنوعی باید در پنل ادمین تنظیم شود.');
        }

        return $this->failure('تحلیل صوتی واقعی برای این ارائه‌دهنده پشتیبانی نمی‌شود. مدل پیش‌فرض را روی OpenAI تنظیم کنید.');
    }

    protected function demoAudioAnalysis(AudioAnalysisRequestData $request, string $model): LlmOperationResult
    {
        $data = [
            'score' => 87,
            'strengths' => [
                'ارتباط مؤثر و همدلی مناسب با مشتری',
                'مدیریت خوب اعتراضات',
                'ارائه مسیر روشن برای حل مشکل',
            ],
            'weaknesses' => [
                'از دست دادن فرصت فروش طرح گارانتی',
                'عدم تأیید ترجیحات تماس مشتری',
            ],
            'next_actions' => [
                'پیگیری با مشتری پس از ارسال جایگزین',
                'مرور اسکریپت فروش گارانتی با کارشناس',
            ],
            'summary' => 'مشتری به‌دلیل دریافت محصول معیوب با مرکز تماس ارتباط گرفت و درخواست جایگزینی یا بازپرداخت داشت. در ابتدای تماس، نارضایتی خود را از کیفیت محصول و تأخیر احتمالی در رسیدگی ابراز کرد و درباره زمان تحویل جایگزین، شرایط تخفیف و نحوه پیگیری سوال پرسید. کارشناس با همدلی به مشکل تأیید کرد، مراحل رسیدگی را شفاف توضیح داد و گزینه‌های جایگزینی همراه با تخفیف تشویقی را ارائه کرد. مشتری نسبت به قیمت نهایی و زمان ارسال نگرانی داشت، اما پس از دریافت پاسخ‌های روشن، تمایل خود را برای ادامه همکاری نشان داد. در پایان تماس، ارسال جایگزین و پیگیری مجدد پس از تحویل به‌عنوان اقدام بعدی توافق شد و کارشناس متعهد به اطلاع‌رسانی وضعیت شد. نتیجه کلی تماس مثبت ارزیابی می‌شود و فرصت حفظ مشتری و تبدیل به خرید بعدی وجود دارد.',
            'sentiment' => 'positive',
            'overall_evaluation' => 'عملکرد قوی با فرصت‌های کوچک آموزشی در زمینه فروش پیشگیرانه.',
            'lead_quality' => [
                'score' => 78,
                'level' => 'high',
                'reason' => 'مشتری تمایل جدی به خرید نشان داد و درباره زمان تحویل و شرایط پرداخت سوال پرسید.',
                'buying_intent_signals' => [
                    'پرسش درباره زمان تحویل محصول',
                    'درخواست اطلاعات تخفیف و شرایط پرداخت',
                    'تأیید نیاز به خرید در کوتاه‌مدت',
                ],
            ],
            'concerns' => [
                [
                    'type' => 'price',
                    'text' => 'نگرانی از قیمت نهایی و درخواست تخفیف بیشتر',
                    'severity' => 'medium',
                ],
                [
                    'type' => 'timing',
                    'text' => 'ابهام در زمان ارسال جایگزین و نگرانی از تأخیر',
                    'severity' => 'high',
                ],
            ],
            'customer_identity' => [
                'person_name' => 'مهدی بشیرپور',
                'company_name' => 'آلفا',
                'confidence' => 0.92,
                'evidence' => 'سلام، من مهدی بشیرپور از شرکت آلفا هستم',
            ],
        ];

        $inputTokens = 4500;
        $outputTokens = 900;

        return LlmOperationResult::success(
            data: $data,
            message: 'تحلیل صوتی با موفقیت انجام شد (حالت نمایشی).',
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: 0,
            durationMs: 2400,
            model: $model,
        );
    }

    abstract public function testConnection(): LlmOperationResult;

    abstract public function analyzeAudio(AudioAnalysisRequestData $request): LlmOperationResult;
}
