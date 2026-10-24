<?php

namespace Tests\Unit;

use App\Application\Llm\Prompts\PromptBuilder;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use PHPUnit\Framework\TestCase;

class PromptBuilderSummaryTest extends TestCase
{
    public function test_summary_policy_requires_detailed_business_summary(): void
    {
        $policy = PromptBuilder::summaryPolicy();

        $this->assertStringContainsString('یک خلاصه کسب‌وکاری مفصل با نثر فارسی روان و رعایت قانون زبان بنویسید', $policy);
        $this->assertStringContainsString('دلیل اصلی تماس', $policy);
        $this->assertStringContainsString('اقدامات بعدی توافق‌شده', $policy);
        $this->assertStringContainsString('یک تا سه پاراگراف', $policy);
        $this->assertStringContainsString('بین اقدام توافق‌شده در مکالمه و پیشنهاد بهبود خودتان تفکیک قائل شوید', $policy);
        $this->assertStringContainsString('اگر نام شخص یا شرکت نامطمئن و تأییدنشده است، نام حدسی ننویسید', $policy);
        $this->assertStringContainsString('متن مکالمه را تکرار نکنید', $policy);
        $this->assertStringNotContainsString('Generate a detailed business summary', $policy);
    }

    public function test_context_prompt_requests_detailed_summary(): void
    {
        $builder = new PromptBuilder;
        $request = new AudioAnalysisRequestData(callId: 1);

        $prompt = $builder->contextPrompt($request);

        $this->assertStringContainsString('خلاصه باید مفصل، کسب‌وکاری و با نثر فارسی مطابق قانون زبان باشد', $prompt);
    }
}
