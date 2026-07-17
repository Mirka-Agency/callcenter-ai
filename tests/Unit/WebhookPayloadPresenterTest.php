<?php

namespace Tests\Unit;

use App\Support\WebhookPayloadPresenter;
use Tests\TestCase;

class WebhookPayloadPresenterTest extends TestCase
{
    public function test_it_redacts_sensitive_values_but_keeps_call_fields_visible(): void
    {
        $formatted = app(WebhookPayloadPresenter::class)->format([
            'event_name' => 'CDR',
            'unique_id' => 'call-123',
            'src' => '09120000000',
            'credentials' => [
                'api_key' => 'secret-api-key',
                'password' => 'secret-password',
            ],
            'Authorization' => 'Bearer secret-token',
        ]);

        $this->assertStringContainsString('"event_name": "CDR"', $formatted);
        $this->assertStringContainsString('"unique_id": "call-123"', $formatted);
        $this->assertStringContainsString('"src": "09120000000"', $formatted);
        $this->assertStringNotContainsString('secret-api-key', $formatted);
        $this->assertStringNotContainsString('secret-password', $formatted);
        $this->assertStringNotContainsString('secret-token', $formatted);
        $this->assertSame(3, substr_count($formatted, '[REDACTED]'));
    }

    public function test_it_redacts_sensitive_query_parameters_in_urls(): void
    {
        $formatted = app(WebhookPayloadPresenter::class)->format([
            'recording_url' => 'https://example.test/audio.mp3?token=secret&call_id=123',
        ]);

        $this->assertStringNotContainsString('token=secret', $formatted);
        $this->assertStringContainsString('token=[REDACTED]', $formatted);
        $this->assertStringContainsString('call_id=123', $formatted);
    }

    public function test_it_limits_large_payload_collections(): void
    {
        $formatted = app(WebhookPayloadPresenter::class)->format([
            'items' => range(1, 150),
        ]);

        $this->assertStringContainsString('"__truncated__": "[TRUNCATED]"', $formatted);
    }
}
