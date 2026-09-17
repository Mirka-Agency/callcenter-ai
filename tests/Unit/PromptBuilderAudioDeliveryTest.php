<?php

namespace Tests\Unit;

use App\Application\Llm\Services\PromptBuilder;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptBuilderAudioDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_audio_messages_embeds_base64_multimodal_payload(): void
    {
        $request = new AudioAnalysisRequestData(callId: 1, sendAudioFile: true, mimeType: 'audio/mpeg');
        $messages = app(PromptBuilder::class)->buildAudioMessages(
            $request,
            'mp3',
            false,
            null,
            'Zm9v',
            'audio/mpeg',
        );

        $userContent = $messages[1]['content'];

        $this->assertSame('input_audio', $userContent[1]['type']);
        $this->assertSame('Zm9v', $userContent[1]['input_audio']['data']);
        $this->assertSame('mp3', $userContent[1]['input_audio']['format']);
        $this->assertSame('audio/mpeg', $userContent[1]['input_audio']['mime_type']);
        $this->assertArrayNotHasKey('url', $userContent[1]['input_audio']);
        $this->assertStringContainsString('تمام مقدارهای متنی را فقط به فارسی بنویسید', $messages[0]['content']);
        $this->assertStringContainsString('نمونه خروجی درست', $messages[0]['content']);
        $this->assertStringNotContainsString('Generate a detailed business summary', $messages[0]['content']);
    }

    public function test_build_audio_messages_uses_playback_url_when_not_sending_file(): void
    {
        $request = new AudioAnalysisRequestData(
            callId: 1,
            sendAudioFile: false,
            playbackUrl: 'https://example.com/recording.mp3',
        );

        $messages = app(PromptBuilder::class)->buildAudioMessages(
            $request,
            'mp3',
            false,
            'https://example.com/recording.mp3',
        );

        $userContent = $messages[1]['content'];

        $this->assertSame('input_audio', $userContent[1]['type']);
        $this->assertSame('https://example.com/recording.mp3', $userContent[1]['input_audio']['url']);
        $this->assertArrayNotHasKey('data', $userContent[1]['input_audio']);
    }
}
