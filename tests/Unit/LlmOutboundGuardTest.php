<?php

namespace Tests\Unit;

use App\Domain\Llm\Exceptions\LlmRemoteDisabledException;
use App\Infrastructure\Llm\LlmOutboundGuard;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmOutboundGuardTest extends TestCase
{
    public function test_allows_avalai_when_remote_analysis_is_enabled(): void
    {
        config([
            'llm.remote_enabled' => true,
            'llm.block_avalai' => false,
            'llm.blocked_hosts' => ['api.avalai.ir', 'avalai.ir'],
        ]);

        $guard = new LlmOutboundGuard;

        $guard->assertRemoteAnalysisEnabled();
        $guard->assertUrlAllowed('https://api.avalai.ir/v1/chat/completions');

        $this->assertTrue($guard->remoteAnalysisEnabled());
    }

    public function test_blocks_avalai_host_even_if_an_api_key_exists(): void
    {
        config([
            'llm.remote_enabled' => true,
            'llm.block_avalai' => true,
            'llm.blocked_hosts' => ['api.avalai.ir', 'avalai.ir', '192.168.2.165'],
        ]);

        $guard = new LlmOutboundGuard;

        $this->expectException(LlmRemoteDisabledException::class);
        $this->expectExceptionMessage('api.avalai.ir');

        $guard->assertUrlAllowed('https://api.avalai.ir/v1/chat/completions');
    }

    public function test_blocks_listed_production_host_without_touching_pbx_recordings(): void
    {
        config([
            'llm.remote_enabled' => false,
            'llm.block_avalai' => true,
            'llm.blocked_hosts' => ['api.avalai.ir', 'avalai.ir', '192.168.2.165'],
        ]);

        $guard = new LlmOutboundGuard;

        $guard->assertUrlAllowed('http://192.168.2.16/mirka-call-recordings/file.wav');

        $this->expectException(LlmRemoteDisabledException::class);
        $guard->assertUrlAllowed('http://192.168.2.165/admin');
    }

    public function test_http_client_does_not_send_requests_to_avalai_when_blocked(): void
    {
        config([
            'llm.remote_enabled' => false,
            'llm.block_avalai' => true,
            'llm.blocked_hosts' => ['api.avalai.ir', 'avalai.ir'],
        ]);

        Http::fake();

        try {
            Http::get('https://api.avalai.ir/v1/models');
            $this->fail('Expected AvalAI HTTP to be blocked.');
        } catch (LlmRemoteDisabledException $e) {
            $this->assertStringContainsString('AvalAI', $e->getMessage());
        }

        Http::assertNothingSent();
    }
}
