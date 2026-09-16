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
    }

    public function test_instructional_policies_do_not_contain_english_sentences(): void
    {
        $prompt = implode("\n", [
            PromptBuilder::persianLanguagePolicy(),
            (new PromptBuilder)->defaultSystemPrompt(),
            PromptBuilder::summaryPolicy(),
            PromptBuilder::organizationDomainPolicy(),
            PromptBuilder::weaknessEvaluationPolicy(),
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
