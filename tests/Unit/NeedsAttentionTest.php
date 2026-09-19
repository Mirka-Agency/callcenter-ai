<?php

namespace Tests\Unit;

use App\Support\NeedsAttention;
use PHPUnit\Framework\TestCase;

class NeedsAttentionTest extends TestCase
{
    public function test_normalizes_explicit_attention_payload(): void
    {
        $result = NeedsAttention::normalize([
            'needed' => true,
            'categories' => ['کارشناس', 'product'],
            'reason' => '  مشتری به عملکرد کارشناس اعتراض دارد  ',
        ]);

        $this->assertTrue($result['needed']);
        $this->assertSame(['agent', 'product'], $result['categories']);
        $this->assertSame('مشتری به عملکرد کارشناس اعتراض دارد', $result['reason']);
    }

    public function test_boolean_true_is_treated_as_needed(): void
    {
        $result = NeedsAttention::normalize(true);

        $this->assertTrue($result['needed']);
        $this->assertSame(['general'], $result['categories']);
        $this->assertNotSame('', $result['reason']);
    }

    public function test_infers_complaint_about_service_from_response(): void
    {
        $result = NeedsAttention::fromResponse([
            'summary' => 'مشتری به کیفیت پشتیبانی اعتراض دارد و می‌گوید سرویس قابل قبول نیست.',
            'sentiment' => 'negative',
            'concerns' => [
                ['type' => 'trust', 'text' => 'اعتراض به پشتیبانی', 'severity' => 'high'],
            ],
        ]);

        $this->assertTrue($result['needed']);
        $this->assertContains('service', $result['categories']);
    }

    public function test_does_not_flag_ordinary_price_concern(): void
    {
        $result = NeedsAttention::fromResponse([
            'summary' => 'مشتری برای استعلام هزینه تمدید اشتراک تماس گرفت.',
            'sentiment' => 'neutral',
            'concerns' => [
                ['type' => 'price', 'text' => 'نگرانی از هزینه تمدید', 'severity' => 'medium'],
            ],
        ]);

        $this->assertFalse($result['needed']);
        $this->assertSame([], $result['categories']);
    }

    public function test_explicit_false_is_not_overridden_by_inference(): void
    {
        $result = NeedsAttention::fromResponse([
            'needs_attention' => [
                'needed' => false,
                'categories' => [],
                'reason' => '',
            ],
            'summary' => 'مشتری اعتراض دارد و شکایت می‌کند.',
            'sentiment' => 'negative',
        ]);

        $this->assertFalse($result['needed']);
    }
}
