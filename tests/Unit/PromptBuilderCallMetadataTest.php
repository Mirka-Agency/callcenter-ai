<?php

namespace Tests\Unit;

use App\Application\Llm\Services\PromptBuilder;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\DTOs\PromptContextData;
use PHPUnit\Framework\TestCase;

class PromptBuilderCallMetadataTest extends TestCase
{
    public function test_context_prompt_places_call_metadata_before_transcript_for_inbound_calls(): void
    {
        $prompt = $this->buildPrompt(new PromptContextData(
            employeeName: 'علی رضایی',
            position: 'کارشناس فروش',
            callDirection: 'inbound',
            organizationName: 'میرکو',
            agentRole: 'کارشناس فروش',
        ));

        $this->assertSame(
            <<<'PROMPT'
سازمان:
میرکو

کارشناس:
علی رضایی

نقش کارشناس:
کارشناس فروش

جهت تماس:
تماس ورودی

زمینه تکمیلی:
زمینه سامانه (این مقدارها هویت مشتری نیستند):
نام کارشناس فعلی سامانه: علی رضایی
این شخص کارشناس سازمان است و نام مشتری نیست؛ حتی اگر در مکالمه خودش را معرفی کند.
نام سازمان فعلی سامانه: میرکو
این نام شرکت ماست و نام شرکت مشتری نیست؛ حتی اگر کارشناس در معرفی آن را بگوید.

متن مکالمه:
فایل صوتی پیوست شده است و متن جداگانه‌ای در دست نیست.

وظیفه:
به فایل صوتی پیوست‌شده گوش دهید و مکالمه را تحلیل کنید.
خلاصه باید مفصل، کسب‌وکاری و کاملاً فارسی باشد.
نام کارشناس و نام سازمان در زمینه تماس را از نام و شرکت مشتری جدا کنید.
اگر موضوع تماس پیگیری پرداخت‌نشده یا بدهی است، کسی که از طرف سازمان پول می‌خواهد کارشناس است و طرف مقابل مشتری است. این درخواست را نارضایتی مشتری حساب نکنید.
پیگیری تلفنی را فقط اگر کارشناس صریحاً قول تماس مجدد با زمان مشخص داده باشد ثبت کنید؛ در غیر این صورت آن فهرست را خالی بگذارید.
فقط خروجی ساخت‌یافته با کلیدهای خواسته‌شده را برگردانید؛ همه مقدارهای متنی فارسی باشند.
PROMPT,
            $prompt,
        );
    }

    public function test_context_prompt_labels_outbound_calls(): void
    {
        $prompt = $this->buildPrompt(new PromptContextData(
            employeeName: 'سارا محمدی',
            callDirection: 'outbound',
            organizationName: 'کلینیک غدد',
            agentRole: 'کارشناس پذیرش',
        ));

        $this->assertStringContainsString("جهت تماس:\nتماس خروجی", $prompt);
        $this->assertStringNotContainsString('تماس ورودی', $prompt);
    }

    public function test_context_prompt_uses_unknown_defaults_without_failing(): void
    {
        $prompt = $this->buildPrompt(new PromptContextData);

        $this->assertStringContainsString("سازمان:\nنامشخص", $prompt);
        $this->assertStringContainsString("کارشناس:\nنامشخص", $prompt);
        $this->assertStringContainsString("نقش کارشناس:\nنامشخص", $prompt);
        $this->assertStringContainsString("جهت تماس:\nنامشخص", $prompt);
        $this->assertStringContainsString('به فایل صوتی پیوست‌شده گوش دهید و مکالمه را تحلیل کنید.', $prompt);
    }

    public function test_context_prompt_falls_back_to_position_for_agent_role(): void
    {
        $prompt = $this->buildPrompt(new PromptContextData(
            position: 'سرپرست تماس',
        ));

        $this->assertStringContainsString("نقش کارشناس:\nسرپرست تماس", $prompt);
    }

    public function test_context_prompt_falls_back_to_position_when_agent_role_is_blank(): void
    {
        $prompt = $this->buildPrompt(new PromptContextData(
            position: 'کارشناس پشتیبانی',
            agentRole: '   ',
        ));

        $this->assertStringContainsString("نقش کارشناس:\nکارشناس پشتیبانی", $prompt);
    }

    public function test_context_prompt_includes_provided_transcript(): void
    {
        $prompt = $this->buildPrompt(new PromptContextData(
            callDirection: 'inbound',
            transcript: "کارشناس: سلام، چطور می‌تونم کمکتون کنم؟\nمشتری: می‌خواستم وضعیت سفارشم را بپرسم.",
        ));

        $this->assertStringContainsString(
            "متن مکالمه:\nکارشناس: سلام، چطور می‌تونم کمکتون کنم؟\nمشتری: می‌خواستم وضعیت سفارشم را بپرسم.",
            $prompt,
        );
        $this->assertStringNotContainsString('فایل صوتی پیوست شده است و متن جداگانه‌ای در دست نیست.', $prompt);
    }

    public function test_context_prompt_avoids_english_instruction_labels(): void
    {
        $prompt = $this->buildPrompt(new PromptContextData(
            organizationName: 'میرکو',
            callDirection: 'inbound',
        ));

        $this->assertStringNotContainsString('Organization:', $prompt);
        $this->assertStringNotContainsString('Analyze this conversation', $prompt);
        $this->assertStringNotContainsString('Attached audio recording', $prompt);
        $this->assertStringNotContainsString('Unknown', $prompt);
        $this->assertStringNotContainsString('Inbound Call', $prompt);
    }

    private function buildPrompt(?PromptContextData $context = null): string
    {
        return (new PromptBuilder)->contextPrompt(new AudioAnalysisRequestData(
            callId: 1,
            context: $context,
        ));
    }
}
