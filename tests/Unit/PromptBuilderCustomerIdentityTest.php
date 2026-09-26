<?php

namespace Tests\Unit;

use App\Application\Llm\Services\PromptBuilder;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\DTOs\PromptContextData;
use PHPUnit\Framework\TestCase;

class PromptBuilderCustomerIdentityTest extends TestCase
{
    public function test_context_prompt_includes_crm_context_in_persian(): void
    {
        $builder = new PromptBuilder;
        $request = new AudioAnalysisRequestData(
            callId: 1,
            context: new PromptContextData(
                employeeName: 'علی رضایی',
                organizationName: 'میرکو',
            ),
        );

        $prompt = $builder->contextPrompt($request);

        $this->assertStringContainsString('زمینه سامانه (این مقدارها هویت مشتری نیستند):', $prompt);
        $this->assertStringContainsString('نام کارشناس فعلی سامانه: علی رضایی', $prompt);
        $this->assertStringContainsString('نام سازمان فعلی سامانه: میرکو', $prompt);
        $this->assertStringNotContainsString('current_user_name', $prompt);
        $this->assertStringNotContainsString('زمینه CRM:', $prompt);
    }

    public function test_customer_identity_policy_includes_crm_exclusion_rules(): void
    {
        $policy = PromptBuilder::customerIdentityPolicy();

        $this->assertStringContainsString('در تماس خروجی', $policy);
        $this->assertStringContainsString('املای درست فارسی', $policy);
        $this->assertStringContainsString('customer_identity', $policy);
        $this->assertStringContainsString('این مقدارها را هویت مشتری ندانید', $policy);
        $this->assertStringContainsString('نام کارشناس فعلی', $policy);
        $this->assertStringContainsString('قوانین تفکیک کارشناس، مشتری و شرکت', $policy);
        $this->assertStringContainsString('person_name را فقط وقتی پر کنید که خود مشتری نامش را گفته باشد', $policy);
        $this->assertStringContainsString('company_name فقط شرکت، برند یا محل کار خود مشتری است', $policy);
        $this->assertStringContainsString('کسی که از طرف سازمان می‌گوید طرف مقابل پرداخت نکرده، کارشناس است', $policy);
        $this->assertStringContainsString('کارشناس برای پیگیری پرداخت با مشتری تماس گرفت', $policy);
        $this->assertStringNotContainsString('Do NOT identify these values as customer information', $policy);
        $this->assertStringNotContainsString('You are provided with the current CRM user name', $policy);
    }
}
