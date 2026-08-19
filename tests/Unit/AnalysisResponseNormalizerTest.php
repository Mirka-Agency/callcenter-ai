<?php

namespace Tests\Unit;

use App\Application\Llm\Services\AnalysisResponseNormalizer;
use PHPUnit\Framework\TestCase;

class AnalysisResponseNormalizerTest extends TestCase
{
    private AnalysisResponseNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new AnalysisResponseNormalizer;
    }

    public function test_normalizes_lead_quality_with_defaults(): void
    {
        $result = $this->normalizer->apply([]);

        $this->assertSame(0, $result['lead_quality']['score']);
        $this->assertSame('low', $result['lead_quality']['level']);
        $this->assertSame('ارزیابی کیفیت لید در دسترس نیست.', $result['lead_quality']['reason']);
        $this->assertSame([], $result['lead_quality']['buying_intent_signals']);
        $this->assertSame([], $result['concerns']);
        $this->assertSame('', $result['customer_identity']['person_name']);
        $this->assertSame('', $result['customer_identity']['company_name']);
        $this->assertSame(0.0, $result['customer_identity']['confidence']);
        $this->assertSame('', $result['customer_identity']['evidence']);
    }

    public function test_normalizes_lead_quality_and_concerns_from_partial_response(): void
    {
        $result = $this->normalizer->apply([
            'lead_quality' => [
                'score' => 150,
                'level' => 'invalid',
                'reason' => '  مشتری جدی است  ',
                'buying_intent_signals' => ['پرسش قیمت', '', 123],
            ],
            'concerns' => [
                ['type' => 'price', 'text' => 'نگرانی از قیمت', 'severity' => 'high'],
                ['type' => 'unknown', 'text' => 'ابهام در تحویل', 'severity' => 'invalid'],
                ['text' => ''],
            ],
        ]);

        $this->assertSame(100, $result['lead_quality']['score']);
        $this->assertSame('high', $result['lead_quality']['level']);
        $this->assertSame('مشتری جدی است', $result['lead_quality']['reason']);
        $this->assertSame(['پرسش قیمت'], $result['lead_quality']['buying_intent_signals']);
        $this->assertCount(2, $result['concerns']);
        $this->assertSame('price', $result['concerns'][0]['type']);
        $this->assertSame('other', $result['concerns'][1]['type']);
        $this->assertSame('medium', $result['concerns'][1]['severity']);
    }

    public function test_normalizes_customer_identity_and_excludes_crm_context(): void
    {
        $result = $this->normalizer->apply([
            'customer_identity' => [
                'person_name' => 'علی رضایی',
                'company_name' => 'میرکو',
                'confidence' => 92,
                'evidence' => '  سلام، من علی رضایی از میرکو هستم  ',
            ],
        ], [
            'current_user_name' => 'علی رضایی',
            'current_company_name' => 'میرکو',
        ]);

        $this->assertSame('', $result['customer_identity']['person_name']);
        $this->assertSame('', $result['customer_identity']['company_name']);
        $this->assertSame(0.0, $result['customer_identity']['confidence']);
        $this->assertSame('سلام، من علی رضایی از میرکو هستم', $result['customer_identity']['evidence']);
    }

    public function test_normalizes_customer_identity_from_conversation(): void
    {
        $result = $this->normalizer->apply([
            'customer_identity' => [
                'person_name' => 'مهدی بشیرپور',
                'company_name' => 'آلفا',
                'confidence' => 0.92,
                'evidence' => 'سلام، من مهدی بشیرپور از شرکت آلفا هستم',
            ],
        ], [
            'current_user_name' => 'علی رضایی',
            'current_company_name' => 'میرکو',
        ]);

        $this->assertSame('مهدی بشیرپور', $result['customer_identity']['person_name']);
        $this->assertSame('آلفا', $result['customer_identity']['company_name']);
        $this->assertSame(0.92, $result['customer_identity']['confidence']);
    }

    public function test_zero_score_without_evaluable_flag_is_not_evaluable(): void
    {
        $result = $this->normalizer->apply(['score' => 0, 'summary' => 'سکوت']);

        $this->assertFalse($result['evaluable']);
        $this->assertSame(0, $result['score']);
        $this->assertSame(0, $result['lead_quality']['score']);
    }

    public function test_explicit_evaluable_false_forces_zero_score(): void
    {
        $result = $this->normalizer->apply([
            'score' => 12,
            'evaluable' => false,
            'summary' => 'تماس گرفته شد ولی صحبت نشد',
        ]);

        $this->assertFalse($result['evaluable']);
        $this->assertSame(0, $result['score']);
    }
}
