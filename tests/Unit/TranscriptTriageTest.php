<?php

namespace Tests\Unit;

use App\Application\Llm\Services\TranscriptTriage;
use App\Domain\Llm\Contracts\CompletesJsonPrompt;
use App\Domain\Llm\Contracts\LlmProviderInterface;
use App\Domain\Llm\DTOs\PromptContextData;
use App\Domain\Llm\ValueObjects\LlmOperationResult;
use Mockery\MockInterface;
use Tests\TestCase;

class TranscriptTriageTest extends TestCase
{
    public function test_low_confidence_keeps_the_full_analysis(): void
    {
        $decision = (new TranscriptTriage)->decide(
            $this->provider([
                'kind' => 'personal',
                'confidence' => 0.4,
                'summary' => 'شاید سفارش غذا باشد.',
            ]),
            'gpt-4o',
            new PromptContextData(transcript: 'کارشناس: سلام'),
        );

        $this->assertFalse($decision->skipFullAnalysis);
        $this->assertSame(6, $decision->inputTokens);
    }

    public function test_a_long_transcript_skips_the_extra_classification_call(): void
    {
        $provider = \Mockery::mock(LlmProviderInterface::class, CompletesJsonPrompt::class);
        $provider->shouldNotReceive('completeJson');

        $decision = (new TranscriptTriage)->decide(
            $provider,
            'gpt-4o',
            new PromptContextData(transcript: str_repeat('کارشناس: سلام. مشتری: بله. ', 40)),
        );

        $this->assertFalse($decision->skipFullAnalysis);
        $this->assertSame(0, $decision->inputTokens);
    }

    public function test_a_confident_empty_call_skips_the_full_analysis(): void
    {
        $decision = (new TranscriptTriage)->decide(
            $this->provider([
                'kind' => 'none',
                'confidence' => 0.91,
                'summary' => 'فقط بوق شنیده شد.',
            ]),
            'gpt-4o',
            new PromptContextData(transcript: 'نامشخص: بوق'),
        );

        $this->assertTrue($decision->skipFullAnalysis);
        $this->assertFalse($decision->payload['is_personal']);
        $this->assertFalse($decision->payload['evaluable']);
        $this->assertSame('فقط بوق شنیده شد.', $decision->payload['summary']);
    }

    /** @param  array<string, mixed>  $payload */
    private function provider(array $payload): LlmProviderInterface
    {
        /** @var LlmProviderInterface&CompletesJsonPrompt&MockInterface $provider */
        $provider = \Mockery::mock(LlmProviderInterface::class, CompletesJsonPrompt::class);
        $provider->shouldReceive('completeJson')->andReturn(LlmOperationResult::success(
            data: $payload,
            inputTokens: 6,
            outputTokens: 3,
        ));

        return $provider;
    }
}
