<?php

namespace Tests\Unit;

use App\Application\Llm\Prompts\SpeakerRolePrompt;
use App\Application\Llm\Prompts\TranscriptTriagePrompt;
use App\Domain\Llm\DTOs\PromptContextData;
use JsonException;
use PHPUnit\Framework\TestCase;

class PromptContextTest extends TestCase
{
    public function test_role_context_preserves_persian_names_quotes_and_transcript_boundaries(): void
    {
        $context = new PromptContextData(
            organizationName: 'شرکت "آزمایشی"',
            employeeName: 'کارشناس نمونه',
            callDirection: 'outbound',
        );
        $transcript = "گوینده ۱: سلام\nگوینده ۲: عبارت \"مشتری\" را گفتم.";

        $payload = json_decode(SpeakerRolePrompt::user($context, $transcript), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame([
            'organization' => $context->organizationName,
            'employee' => $context->employeeName,
            'direction' => 'outbound',
            'transcript' => $transcript,
        ], $payload);
    }

    public function test_invalid_transcript_encoding_fails_before_sending_an_invalid_role_request(): void
    {
        $this->expectException(JsonException::class);

        SpeakerRolePrompt::user(new PromptContextData, "\xB1\x31");
    }

    public function test_triage_omits_blank_context_without_removing_transcript_content(): void
    {
        $transcript = "کارشناس: سلام\nمشتری: برای پشتیبانی تماس گرفتم.";
        $context = new PromptContextData(
            organizationName: ' ',
            employeeName: "\t",
            callDirection: '',
            organizationBusinessContext: 'نرم‌افزار',
        );

        $prompt = TranscriptTriagePrompt::user($context, $transcript);

        $this->assertStringContainsString('زمینه فعالیت سازمان: نرم‌افزار', $prompt);
        $this->assertStringEndsWith("متن مکالمه:\n{$transcript}", $prompt);
        $this->assertStringNotContainsString("\nسازمان:", $prompt);
        $this->assertStringNotContainsString('جهت تماس:', $prompt);
        $this->assertStringNotContainsString("کارشناس: \t", $prompt);
    }
}
