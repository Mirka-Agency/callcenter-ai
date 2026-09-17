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

        $this->assertStringContainsString('customer_identity', $policy);
        $this->assertStringContainsString('این مقدارها را هویت مشتری ندانید', $policy);
        $this->assertStringContainsString('نام کارشناس فعلی', $policy);
        $this->assertStringNotContainsString('Do NOT identify these values as customer information', $policy);
        $this->assertStringNotContainsString('You are provided with the current CRM user name', $policy);
    }
}
