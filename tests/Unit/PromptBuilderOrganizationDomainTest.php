<?php

namespace Tests\Unit;

use App\Application\Llm\Services\PromptBuilder;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\DTOs\PromptContextData;
use PHPUnit\Framework\TestCase;

class PromptBuilderOrganizationDomainTest extends TestCase
{
    public function test_context_prompt_includes_organization_business_context(): void
    {
        $builder = new PromptBuilder;
        $request = new AudioAnalysisRequestData(
            callId: 1,
            context: new PromptContextData(
                organizationName: 'کلینیک غدد',
                organizationBusinessContext: 'کلینیک غدد درون‌ریز. خدمات: تیروئید، دیابت. لیزر پوست نیست.',
            ),
        );

        $prompt = $builder->contextPrompt($request);

        $this->assertStringContainsString('سازمان: کلینیک غدد', $prompt);
        $this->assertStringContainsString('زمینه فعالیت سازمان: کلینیک غدد درون‌ریز. خدمات: تیروئید، دیابت. لیزر پوست نیست.', $prompt);
    }

    public function test_context_prompt_omits_blank_business_context(): void
    {
        $builder = new PromptBuilder;
        $request = new AudioAnalysisRequestData(
            callId: 1,
            context: new PromptContextData(
                organizationName: 'میرکو',
                organizationBusinessContext: '   ',
            ),
        );

        $prompt = $builder->contextPrompt($request);

        $this->assertStringContainsString('سازمان: میرکو', $prompt);
        $this->assertStringNotContainsString('زمینه فعالیت سازمان:', $prompt);
    }

    public function test_organization_domain_policy_requires_domain_alignment(): void
    {
        $policy = PromptBuilder::organizationDomainPolicy();

        $this->assertStringContainsString('organization business context', $policy);
        $this->assertStringContainsString('phonetically similar speech', $policy);
        $this->assertStringContainsString('endocrine clinic', $policy);
        $this->assertStringContainsString('زمینه فعالیت سازمان', $policy);
        $this->assertStringContainsString('تخصص یا خدمات نامرتبط اختراع نکنید', $policy);
    }
}
