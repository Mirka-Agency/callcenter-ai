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
        $this->assertFalse($result['needs_attention']['needed']);
        $this->assertSame([], $result['needs_attention']['categories']);
        $this->assertSame('', $result['needs_attention']['reason']);
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

    public function test_excludes_own_company_when_prefixed_or_arabic_spelled(): void
    {
        $result = $this->normalizer->apply([
            'customer_identity' => [
                'person_name' => 'مهدی بشیرپور',
                'company_name' => 'شركت ميركو',
                'confidence' => 0.9,
                'evidence' => 'از شرکت میرکو تماس می‌گیریم',
            ],
        ], [
            'current_user_name' => 'علی رضایی',
            'current_company_name' => 'میرکو',
        ]);

        $this->assertSame('مهدی بشیرپور', $result['customer_identity']['person_name']);
        $this->assertSame('', $result['customer_identity']['company_name']);
    }

    public function test_rewrites_company_name_with_correct_persian_letters(): void
    {
        $result = $this->normalizer->apply([
            'customer_identity' => [
                'person_name' => 'سارا محمدی',
                'company_name' => 'شركت  آلفا',
                'confidence' => 0.8,
            ],
        ], [
            'current_company_name' => 'میرکو',
        ]);

        $this->assertSame('شرکت آلفا', $result['customer_identity']['company_name']);
    }

    public function test_zero_score_without_evaluable_flag_is_not_evaluable(): void
    {
        $result = $this->normalizer->apply(['score' => 0, 'summary' => 'سکوت']);

        $this->assertFalse($result['evaluable']);
        $this->assertSame(0, $result['score']);
        $this->assertSame(0, $result['lead_quality']['score']);
    }

    public function test_accepts_persian_aliases_for_levels_and_concern_types(): void
    {
        $result = $this->normalizer->apply([
            'lead_quality' => [
                'score' => 55,
                'level' => 'متوسط',
                'reason' => 'مشتری در حال مقایسه قیمت است',
            ],
            'concerns' => [
                ['type' => 'قیمت', 'text' => 'گران است', 'severity' => 'بالا'],
                ['type' => 'اعتماد', 'text' => 'نگران کیفیت است', 'severity' => 'کم'],
            ],
        ]);

        $this->assertSame('medium', $result['lead_quality']['level']);
        $this->assertSame('price', $result['concerns'][0]['type']);
        $this->assertSame('high', $result['concerns'][0]['severity']);
        $this->assertSame('trust', $result['concerns'][1]['type']);
        $this->assertSame('low', $result['concerns'][1]['severity']);
    }

    public function test_normalizes_explicit_needs_attention(): void
    {
        $result = $this->normalizer->apply([
            'needs_attention' => [
                'needed' => true,
                'categories' => ['agent', 'محصول'],
                'reason' => 'مشتری به عملکرد کارشناس و محصول اعتراض دارد',
            ],
        ]);

        $this->assertTrue($result['needs_attention']['needed']);
        $this->assertSame(['agent', 'product'], $result['needs_attention']['categories']);
        $this->assertSame('مشتری به عملکرد کارشناس و محصول اعتراض دارد', $result['needs_attention']['reason']);
    }

    public function test_payment_collection_call_is_not_stored_as_customer_dissatisfaction(): void
    {
        $result = $this->normalizer->apply([
            'score' => 70,
            'sentiment' => 'negative',
            'summary' => 'مشتری به‌خاطر پرداخت‌نشدن فاکتور تماس گرفت و ناراضی بود.',
            'customer_insights' => ['sentiment' => 'negative', 'intent' => 'اعتراض به عدم پرداخت'],
            'concerns' => [
                ['type' => 'other', 'text' => 'مشتری بدهی را پرداخت نکرده است', 'severity' => 'high'],
            ],
            'needs_attention' => [
                'needed' => true,
                'categories' => ['general'],
                'reason' => 'پیگیری بدهی مشتری',
            ],
        ]);

        $this->assertSame('neutral', $result['sentiment']);
        $this->assertSame('neutral', $result['customer_insights']['sentiment']);
        $this->assertSame([], $result['concerns']);
        $this->assertFalse($result['needs_attention']['needed']);
    }

    public function test_real_service_complaint_stays_negative_even_if_payment_is_mentioned(): void
    {
        $result = $this->normalizer->apply([
            'score' => 40,
            'sentiment' => 'negative',
            'summary' => 'مشتری از کیفیت محصول ناراضی است و فاکتور را پرداخت نکرده تا محصول اصلاح شود.',
            'concerns' => [
                ['type' => 'other', 'text' => 'کیفیت محصول پایین است', 'severity' => 'high'],
            ],
        ]);

        $this->assertSame('negative', $result['sentiment']);
        $this->assertCount(1, $result['concerns']);
    }

    public function test_employee_name_with_honorific_is_not_stored_as_customer(): void
    {
        $result = $this->normalizer->apply([
            'customer_identity' => [
                'person_name' => 'آقای علی رضایی',
                'confidence' => 0.9,
            ],
        ], [
            'current_user_name' => 'علی رضایی',
        ]);

        $this->assertSame('', $result['customer_identity']['person_name']);
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
