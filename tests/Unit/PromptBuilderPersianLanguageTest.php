<?php

namespace Tests\Unit;

use App\Application\Llm\Services\PromptBuilder;
use PHPUnit\Framework\TestCase;

class PromptBuilderPersianLanguageTest extends TestCase
{
    public function test_language_policy_requires_persian_values_and_keeps_schema_keys(): void
    {
        $policy = PromptBuilder::persianLanguagePolicy();

        $this->assertStringContainsString('تمام مقدارهای متنی را فقط به فارسی بنویسید', $policy);
        $this->assertStringContainsString('هیچ جمله، عبارت یا توضیح انگلیسی ننویسید', $policy);
        $this->assertStringContainsString('نام کلیدهای خروجی را عوض نکنید', $policy);
        $this->assertStringContainsString('positive یا neutral یا negative یا mixed', $policy);
        $this->assertStringNotContainsString('حتی نام بخش‌ها و لیبل‌ها هم فارسی باشند', $policy);
    }

    public function test_output_sample_is_persian_with_required_schema(): void
    {
        $sample = PromptBuilder::persianOutputSample();

        $this->assertStringContainsString('نمونه خروجی درست', $sample);
        $this->assertStringContainsString('"summary": "مشتری برای استعلام هزینه تمدید اشتراک تماس گرفت.', $sample);
        $this->assertStringContainsString('"overall_evaluation": "کارشناس مؤدب و مسلط بود', $sample);
        $this->assertStringContainsString('"intent": "استعلام هزینه و شرایط تمدید اشتراک"', $sample);
        $this->assertStringContainsString('"sentiment": "neutral"', $sample);
        $this->assertStringContainsString('"level": "medium"', $sample);
        $this->assertStringContainsString('"needs_attention"', $sample);
        $this->assertStringContainsString('"needed": false', $sample);
    }

    public function test_attention_policy_requires_complaint_detection(): void
    {
        $policy = PromptBuilder::attentionPolicy();

        $this->assertStringContainsString('needs_attention', $policy);
        $this->assertStringContainsString('اعتراض به عملکرد کارشناس', $policy);
        $this->assertStringContainsString('اعتراض به محصول', $policy);
        $this->assertStringContainsString('اعتراض به سرویس', $policy);
    }

    public function test_follow_up_policy_requires_phone_callback_only(): void
    {
        $policy = PromptBuilder::followUpPolicy();

        $this->assertStringContainsString('follow_up_suggestions فقط برای تماس تلفنی برگشتی', $policy);
        $this->assertStringContainsString('کارشناس باید دوباره با همان مشتری تلفنی تماس بگیرد', $policy);
        $this->assertStringContainsString('ارسال فایل، سند، کاتالوگ، پیش‌فاکتور', $policy);
        $this->assertStringContainsString('واتساپ، تلگرام، اینستاگرام', $policy);
        $this->assertStringContainsString('ثبت تیکت، پیگیری مشکل سیستمی', $policy);
        $this->assertStringContainsString('آرایه خالی بگذارید', $policy);
    }

    public function test_system_prompt_includes_follow_up_policy(): void
    {
        $prompt = (new PromptBuilder)->systemPrompt();

        $this->assertStringContainsString(PromptBuilder::followUpPolicy(), $prompt);
        $this->assertStringContainsString('فقط تماس تلفنی برگشتی با مشتری', $prompt);
        $this->assertStringContainsString('"follow_up_suggestions": ["تماس پیگیری در روز بعد برای اعلام تصمیم مشتری"]', $prompt);
    }

    public function test_sentiment_policy_requires_brand_product_or_service_dissatisfaction(): void
    {
        $policy = PromptBuilder::sentimentPolicy();

        $this->assertStringContainsString('احساس مشتری نسبت به برند، محصول و خدمات', $policy);
        $this->assertStringContainsString('negative را فقط و فقط وقتی بگذارید', $policy);
        $this->assertStringContainsString('اگر مشتری اعتراضی نسبت به محصول یا برند ما دارد، حتماً negative بگذارید', $policy);
        $this->assertStringContainsString('نارضایتی از لحن یا عملکرد کارشناس', $policy);
        $this->assertStringContainsString('استعلام قیمت', $policy);
        $this->assertStringContainsString('مقدار را neutral بگذارید', $policy);
        $this->assertStringNotContainsString('overall emotional tone', $policy);
    }

    public function test_system_prompt_includes_sentiment_policy(): void
    {
        $prompt = (new PromptBuilder)->systemPrompt();

        $this->assertStringContainsString(PromptBuilder::sentimentPolicy(), $prompt);
        $this->assertStringContainsString('احساس مشتری نسبت به برند، محصول و خدمات سازمان', $prompt);
        $this->assertStringContainsString('negative فقط در صورت نارضایتی یا اعتراض به برند/محصول/خدمات', $prompt);
    }

    public function test_instructional_policies_do_not_contain_english_sentences(): void
    {
        $prompt = implode("\n", [
            PromptBuilder::persianLanguagePolicy(),
            (new PromptBuilder)->defaultSystemPrompt(),
            PromptBuilder::summaryPolicy(),
            PromptBuilder::organizationDomainPolicy(),
            PromptBuilder::weaknessEvaluationPolicy(),
            PromptBuilder::leadAnalysisPolicy(),
            PromptBuilder::sentimentPolicy(),
            PromptBuilder::attentionPolicy(),
            PromptBuilder::followUpPolicy(),
            PromptBuilder::customerIdentityPolicy(),
            PromptBuilder::persianStrictRetryPolicy(),
        ]);

        foreach ([
            'Generate a detailed business summary',
            'You are provided with the current CRM user name',
            'When organization business context is provided',
            'Analyze this conversation based on the provided context',
            'Do NOT identify these values as customer information',
            'You are a professional',
        ] as $english) {
            $this->assertStringNotContainsString($english, $prompt);
        }
    }
}
