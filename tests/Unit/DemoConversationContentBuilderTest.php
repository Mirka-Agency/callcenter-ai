<?php

namespace Tests\Unit;

use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Models\Customer;
use App\Models\OrganizationUser;
use App\Support\Seeding\DemoConversationContentBuilder;
use Tests\TestCase;

class DemoConversationContentBuilderTest extends TestCase
{
    public function test_builds_complete_analysis_payload(): void
    {
        $employee = new OrganizationUser([
            'first_name' => 'علی',
            'last_name' => 'محمدی',
        ]);

        $customer = new Customer([
            'name' => 'شرکت آفتاب',
            'company_name' => 'شرکت آفتاب',
        ]);

        $payload = (new DemoConversationContentBuilder)->build(
            seed: 42,
            score: 82,
            sentiment: AnalysisSentiment::Positive,
            employee: $employee,
            customer: $customer,
            direction: 'inbound',
            durationSeconds: 420,
            organizationTitle: 'مرکز تماس پارسین',
        );

        $this->assertNotSame('', trim($payload['transcript']));
        $this->assertStringContainsString('کارشناس', $payload['transcript']);
        $this->assertStringContainsString('مشتری', $payload['transcript']);
        $this->assertGreaterThan(120, mb_strlen($payload['summary']));
        $this->assertArrayHasKey('communication_skills', $payload['performance_dimensions_json']);
        $this->assertNotEmpty($payload['customer_insights_json']['intent']);
        $this->assertNotEmpty($payload['operational_insights_json']['follow_up_suggestions']);
        $this->assertArrayHasKey('buying_intent_signals', $payload['lead_quality_json']);
        $this->assertArrayHasKey('needs_attention', $payload);
        $this->assertArrayHasKey('needed', $payload['attention_json']);
        $this->assertContains($payload['metadata']['outcome'], ['success', 'follow_up', 'escalated', 'failed']);
    }
}
