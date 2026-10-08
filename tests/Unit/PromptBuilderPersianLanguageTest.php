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
        $this->assertStringContainsString('مقدار sentiment این نمونه را کپی نکنید', $sample);
        $this->assertStringContainsString('"summary": "مشتری برای استعلام هزینه تمدید اشتراک تماس گرفت.', $sample);
        $this->assertStringContainsString('"overall_evaluation": "کارشناس مؤدب و مسلط بود', $sample);
        $this->assertStringContainsString('"intent": "استعلام هزینه و شرایط تمدید اشتراک"', $sample);
        $this->assertStringContainsString('"sentiment": "neutral"', $sample);
        $this->assertStringContainsString('"level": "medium"', $sample);
        $this->assertStringContainsString('"needs_attention"', $sample);
        $this->assertStringContainsString('"needed": false', $sample);
        $this->assertStringContainsString('"is_personal": false', $sample);
        $this->assertStringContainsString('"personal_reason": ""', $sample);
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
        $this->assertStringContainsString('فهرست پیگیری فراموش‌شده', $policy);
        $this->assertStringContainsString('فقط قول صریح خود کارشناس', $policy);
        $this->assertStringContainsString('مشتری گفت خودش زنگ می‌زند', $policy);
        $this->assertStringContainsString('موعد را حدس نزنید', $policy);
        $this->assertStringContainsString('ارجاع به داخلی یا بخش دیگر همان شرکت', $policy);
        $this->assertStringContainsString('تماس پیگیری ۳ روز دیگر برای اتصال به بخش یا داخلی معرفی‌شده', $policy);
        $this->assertStringContainsString('این نقطه ضعف «عدم پیگیری» نیست', PromptBuilder::weaknessEvaluationPolicy());
        $this->assertStringContainsString('مشتری یا طرف مقابل اصلاً پاسخ نداده', PromptBuilder::evaluableConversationPolicy());
    }

    public function test_personal_call_policy_separates_private_calls_from_work(): void
    {
        $policy = PromptBuilder::personalCallPolicy();

        $this->assertStringContainsString('اگر کارشناس در این تماس مشتریِ طرف مقابل است', $policy);
        $this->assertStringContainsString('سفارش غذا، رستوران، کافه', $policy);
        $this->assertStringContainsString('خریدن چیزی برای خودش', $policy);
        $this->assertStringContainsString('زمینه فعالیت سازمان را روی این تماس‌ها اعمال نکنید', $policy);
        $this->assertStringContainsString('اگر حتی بخشی از مکالمه فروش یا پشتیبانی خود سازمان به مشتری سازمان است', $policy);
        $this->assertStringContainsString('اگر کارشناس خریدار است و معلوم نیست کالا برای سازمان است یا برای خودش، is_personal را درست بگذارید', $policy);
        $this->assertStringNotContainsString('موضوع غذا یا خرید روزمره شخصی نیست، is_personal را نادرست بگذارید', $policy);
        $this->assertStringContainsString('جزئیات خصوصی', $policy);
        $this->assertStringContainsString('coaching_analysis را تهی بگذارید', $policy);
        $this->assertStringContainsString('customer_identity را خالی بگذارید', $policy);
    }

    public function test_system_prompt_includes_personal_call_policy(): void
    {
        $prompt = (new PromptBuilder)->systemPrompt();

        $this->assertStringContainsString(PromptBuilder::personalCallPolicy(), $prompt);
        $this->assertStringContainsString('is_personal (درست اگر کارشناس از خط شرکت برای کار شخصی استفاده کرده', $prompt);
        $this->assertStringContainsString('اگر کارشناس خریدار است و از شخص، فروشگاه، رستوران یا شرکت دیگری کالا یا خدمت می‌خرد، این فرصت معاملاتی نیست', $prompt);
    }

    public function test_system_prompt_includes_follow_up_policy(): void
    {
        $prompt = (new PromptBuilder)->systemPrompt();

        $this->assertStringContainsString(PromptBuilder::followUpPolicy(), $prompt);
        $this->assertStringContainsString(PromptBuilder::coachingPolicy(), $prompt);
        $this->assertStringContainsString('فقط رفتار کارشناس را ارزیابی کنید', $prompt);
        $this->assertStringContainsString('need_discovery', $prompt);
        $this->assertStringContainsString('فقط قول صریح کارشناس برای تماس تلفنی برگشتی با همین مشتری', $prompt);
        $this->assertStringContainsString('"follow_up_suggestions": ["تماس پیگیری در روز بعد برای اعلام تصمیم مشتری"]', $prompt);
    }

    public function test_sentiment_policy_reads_customer_emotion_and_keeps_collection_calls_neutral(): void
    {
        $policy = PromptBuilder::sentimentPolicy();

        $this->assertStringContainsString('حال‌وهوای عاطفی خود مشتری', $policy);
        $this->assertStringContainsString('مقدار neutral نمونه خروجی را کپی نکنید', $policy);
        $this->assertStringContainsString('اگر مشتری اعتراضی نسبت به محصول یا برند ما دارد، حتماً negative بگذارید', $policy);
        $this->assertStringContainsString('ناراحتی واقعی از عملکرد کارشناس negative است', $policy);
        $this->assertStringContainsString('استعلام خشک قیمت', $policy);
        $this->assertStringContainsString('پیگیری پرداخت‌نشده', $policy);
        $this->assertStringContainsString('اگر نقش کارشناس و مشتری را عوض کنید', $policy);
        $this->assertStringContainsString('مکالمه را فقط به‌خاطر معمولی بودن فروش یا پشتیبانی خنثی نکنید', $policy);
        $this->assertStringNotContainsString('در سایر مکالمات معمولی فروش یا پشتیبانی، مقدار را neutral بگذارید', $policy);
        $this->assertStringNotContainsString('overall emotional tone', $policy);
    }

    public function test_system_prompt_includes_sentiment_policy(): void
    {
        $prompt = (new PromptBuilder)->systemPrompt();

        $this->assertStringContainsString(PromptBuilder::sentimentPolicy(), $prompt);
        $this->assertStringContainsString('حال‌وهوای عاطفی خود مشتری در همین تماس', $prompt);
        $this->assertStringContainsString('مقدار نمونه را کپی نکنید', $prompt);
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
            PromptBuilder::personalCallPolicy(),
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
