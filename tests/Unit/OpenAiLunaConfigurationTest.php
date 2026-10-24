<?php

namespace Tests\Unit;

use App\Application\Llm\Services\LlmConnectionResolver;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\DTOs\PromptContextData;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Infrastructure\Llm\Adapters\OpenAiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiLunaConfigurationTest extends TestCase
{
    private OpenAiProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'llm.provider' => 'openai',
            'llm.model' => 'gpt-6-luna',
            'llm.base_url' => 'https://api.avalai.ir/v1',
            'llm.api_key' => 'test-key',
            'llm.remote_enabled' => true,
            'llm.block_avalai' => false,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.avalai.ir/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'summary' => 'مشتری برای پشتیبانی تماس گرفت.',
                    'roles' => ['1' => 'agent', '2' => 'customer'],
                ], JSON_UNESCAPED_UNICODE)], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ]),
        ]);

        [$configuration, $provider] = app(LlmConnectionResolver::class)->resolve(42);
        $this->assertSame(LlmProviderCode::OpenAi, $configuration->providerCode);
        $this->assertSame('gpt-6-luna', $configuration->settings->defaultModel);
        $this->assertInstanceOf(OpenAiProvider::class, $provider);
        $this->provider = $provider;
    }

    public function test_analysis_uses_luna_with_the_benchmarked_reasoning_and_token_budget(): void
    {
        $result = $this->provider->analyzeAudio(new AudioAnalysisRequestData(
            callId: 1,
            context: new PromptContextData(transcript: 'کارشناس: سلام، بفرمایید. مشتری: برای پشتیبانی تماس گرفتم.'),
        ));

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame('مشتری برای پشتیبانی تماس گرفت.', $result->data['summary']);
        $this->assertLunaRequest(8192);
    }

    public function test_role_mapping_preserves_its_own_output_budget(): void
    {
        $result = $this->provider->completeJson('gpt-6-luna', 'Return JSON speaker roles.', 'گوینده ۱: سلام', 2048);

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame(['1' => 'agent', '2' => 'customer'], $result->data['roles']);
        $this->assertLunaRequest(2048);
    }

    private function assertLunaRequest(int $maxOutputTokens): void
    {
        Http::assertSent(function ($request) use ($maxOutputTokens) {
            $data = $request->data();

            return $request->url() === 'https://api.avalai.ir/v1/chat/completions'
                && $data['model'] === 'gpt-6-luna'
                && $data['max_completion_tokens'] === $maxOutputTokens
                && $data['reasoning_effort'] === 'low'
                && $data['response_format']['type'] === 'json_object'
                && ! array_key_exists('temperature', $data)
                && ! array_key_exists('max_tokens', $data);
        });
        Http::assertSentCount(1);
    }
}
