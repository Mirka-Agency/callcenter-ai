<?php

namespace Tests\Unit;

use App\Application\Llm\Services\PromptBuilder;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\DTOs\PromptContextData;
use PHPUnit\Framework\TestCase;

class PromptBuilderCallMetadataTest extends TestCase
{
    public function test_context_prompt_places_call_metadata_before_transcript_for_inbound_calls(): void
    {
        $prompt = $this->buildPrompt(new PromptContextData(
            employeeName: 'علی رضایی',
            position: 'کارشناس فروش',
            callDirection: 'inbound',
            organizationName: 'میرکو',
            agentRole: 'کارشناس فروش',
        ));

        $this->assertSame(
            <<<'PROMPT'
Organization:
میرکو

Agent:
علی رضایی

Agent Role:
کارشناس فروش

Call Direction:
Inbound Call (تماس ورودی)

Additional Context:
زمینه CRM: {"current_user_name":"علی رضایی","current_company_name":"میرکو"}

Conversation Transcript:
Attached audio recording (no separate transcript).

Task:
Analyze this conversation based on the provided context.
مکالمه صوتی پیوست‌شده را تحلیل کن. خلاصه (summary) باید مفصل و کسب‌وکاری باشد. JSON خواسته‌شده را فقط به فارسی برگردان.
PROMPT,
            $prompt,
        );
    }

    public function test_context_prompt_labels_outbound_calls(): void
    {
        $prompt = $this->buildPrompt(new PromptContextData(
            employeeName: 'سارا محمدی',
            callDirection: 'outbound',
            organizationName: 'کلینیک غدد',
            agentRole: 'کارشناس پذیرش',
        ));

        $this->assertStringContainsString("Call Direction:\nOutbound Call (تماس خروجی)", $prompt);
        $this->assertStringNotContainsString('Inbound Call', $prompt);
    }

    public function test_context_prompt_uses_unknown_defaults_without_failing(): void
    {
        $prompt = $this->buildPrompt(new PromptContextData);

        $this->assertStringContainsString("Organization:\nUnknown", $prompt);
        $this->assertStringContainsString("Agent:\nUnknown", $prompt);
        $this->assertStringContainsString("Agent Role:\nUnknown", $prompt);
        $this->assertStringContainsString("Call Direction:\nUnknown", $prompt);
        $this->assertStringContainsString('Analyze this conversation based on the provided context.', $prompt);
    }

    public function test_context_prompt_falls_back_to_position_for_agent_role(): void
    {
        $prompt = $this->buildPrompt(new PromptContextData(
            position: 'سرپرست تماس',
        ));

        $this->assertStringContainsString("Agent Role:\nسرپرست تماس", $prompt);
    }

    public function test_context_prompt_falls_back_to_position_when_agent_role_is_blank(): void
    {
        $prompt = $this->buildPrompt(new PromptContextData(
            position: 'کارشناس پشتیبانی',
            agentRole: '   ',
        ));

        $this->assertStringContainsString("Agent Role:\nکارشناس پشتیبانی", $prompt);
    }

    public function test_context_prompt_includes_provided_transcript(): void
    {
        $prompt = $this->buildPrompt(new PromptContextData(
            callDirection: 'inbound',
            transcript: "Agent: سلام، چطور می‌تونم کمکتون کنم؟\nCustomer: می‌خواستم وضعیت سفارشم را بپرسم.",
        ));

        $this->assertStringContainsString(
            "Conversation Transcript:\nAgent: سلام، چطور می‌تونم کمکتون کنم؟\nCustomer: می‌خواستم وضعیت سفارشم را بپرسم.",
            $prompt,
        );
        $this->assertStringNotContainsString('Attached audio recording (no separate transcript).', $prompt);
    }

    private function buildPrompt(?PromptContextData $context = null): string
    {
        return (new PromptBuilder)->contextPrompt(new AudioAnalysisRequestData(
            callId: 1,
            context: $context,
        ));
    }
}
