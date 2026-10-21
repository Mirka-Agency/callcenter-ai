<?php

namespace Tests\Unit;

use App\Application\Llm\Services\SpeakerTurnLabeler;
use App\Domain\Llm\Contracts\CompletesJsonPrompt;
use App\Domain\Llm\Contracts\LlmProviderInterface;
use App\Domain\Llm\DTOs\PromptContextData;
use App\Domain\Llm\ValueObjects\LlmOperationResult;
use Mockery\MockInterface;
use Tests\TestCase;

class SpeakerTurnLabelerTest extends TestCase
{
    public function test_whisper_text_is_split_into_agent_and_customer_turns(): void
    {
        $provider = $this->provider([
            'transcript' => "کارشناس: سلام، میرکو هستم. علی رضایی هستم.\nمشتری: سفارشم نرسیده است.",
        ]);

        $result = (new SpeakerTurnLabeler)->label(
            $provider,
            'gemini-3.8-flash',
            new PromptContextData(
                employeeName: 'علی رضایی',
                organizationName: 'میرکو',
                callDirection: 'inbound',
            ),
            'سلام، میرکو هستم. علی رضایی هستم. سفارشم نرسیده است.',
        );

        $this->assertSame(
            "کارشناس: سلام، میرکو هستم. علی رضایی هستم.\nمشتری: سفارشم نرسیده است.",
            $result['transcript'],
        );
        $this->assertSame(12, $result['inputTokens']);
    }

    public function test_a_labeled_transcript_is_not_sent_to_the_model_again(): void
    {
        $provider = \Mockery::mock(LlmProviderInterface::class, CompletesJsonPrompt::class);
        $provider->shouldNotReceive('completeJson');

        $result = (new SpeakerTurnLabeler)->label(
            $provider,
            'gemini-3.8-flash',
            new PromptContextData,
            "کارشناس: سلام\nمشتری: ممنون",
        );

        $this->assertSame("کارشناس: سلام\nمشتری: ممنون", $result['transcript']);
        $this->assertSame(0, $result['inputTokens']);
    }

    public function test_a_summary_is_rejected_and_the_whisper_text_stays(): void
    {
        $source = 'سلام، میرکو هستم. علی رضایی هستم. سفارشم نرسیده و سه روز است منتظرم.';
        $provider = $this->provider([
            'transcript' => 'کارشناس: سلام.',
        ]);

        $result = (new SpeakerTurnLabeler)->label(
            $provider,
            'gemini-3.8-flash',
            new PromptContextData(employeeName: 'علی رضایی', organizationName: 'میرکو'),
            $source,
        );

        $this->assertSame($source, $result['transcript']);
    }

    /** @param  array<string, mixed>  $payload */
    private function provider(array $payload): LlmProviderInterface
    {
        /** @var LlmProviderInterface&CompletesJsonPrompt&MockInterface $provider */
        $provider = \Mockery::mock(LlmProviderInterface::class, CompletesJsonPrompt::class);
        $provider->shouldReceive('completeJson')->once()->andReturn(LlmOperationResult::success(
            data: $payload,
            inputTokens: 12,
            outputTokens: 8,
        ));

        return $provider;
    }
}
