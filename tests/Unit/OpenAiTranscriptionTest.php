<?php

namespace Tests\Unit;

use App\Application\Llm\Services\TranscriptionPrompt;
use App\Domain\Llm\DTOs\LlmConnectionConfig;
use App\Domain\Llm\DTOs\LlmCredentials;
use App\Domain\Llm\DTOs\LlmSettings;
use App\Domain\Llm\DTOs\TranscriptionRequestData;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Infrastructure\Llm\Adapters\OpenAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiTranscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_native_openai_transcribes_with_whisper(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'example.com')) {
                return Http::response('audio-bytes');
            }

            $this->assertStringContainsString('/audio/transcriptions', $request->url());
            $body = $request->body();
            $this->assertStringContainsString('whisper-1', $body);
            $this->assertStringContainsString('verbose_json', $body);
            $this->assertStringContainsString('name="language"', $body);

            return Http::response([
                'text' => 'ignored',
                'segments' => [
                    ['text' => 'سلام، شرکت آوایار هستم'],
                    ['text' => 'سفارشم نرسیده'],
                ],
            ]);
        });

        $provider = $this->provider();

        $result = $provider->transcribe(new TranscriptionRequestData(
            callId: 4,
            recordingUrl: 'https://example.com/recording.mp3',
            fallbackModel: 'gpt-4o',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(
            "سلام، شرکت آوایار هستم\nسفارشم نرسیده",
            $result->data['transcript'] ?? null,
        );
        $this->assertSame('whisper-1', $result->model);
    }

    public function test_gateway_transcribes_with_whisper_instead_of_the_analysis_model(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'example.com/recording.mp3')) {
                return Http::response('audio-bytes');
            }

            $this->assertStringContainsString('/audio/transcriptions', $request->url());
            $this->assertStringContainsString('whisper-1', $request->body());
            $this->assertStringNotContainsString('gemini-3.8-flash', $request->body());

            return Http::response([
                'text' => "کارشناس: سلام\nمشتری: ممنون",
            ]);
        });

        $provider = $this->provider('https://gateway.test/v1');

        $result = $provider->transcribe(new TranscriptionRequestData(
            callId: 8,
            recordingUrl: 'https://example.com/recording.mp3',
            fallbackModel: 'gemini-3.8-flash',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame("کارشناس: سلام\nمشتری: ممنون", $result->data['transcript'] ?? null);
        $this->assertSame('whisper-1', $result->model);
    }

    public function test_explicit_transcription_model_is_sent_to_the_speech_api(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'example.com')) {
                return Http::response('audio-bytes');
            }

            $this->assertStringContainsString('gpt-4o-transcribe', $request->body());
            $this->assertStringNotContainsString('whisper-1', $request->body());

            return Http::response(['text' => 'سلام']);
        });

        $result = $this->provider()->transcribe(new TranscriptionRequestData(
            callId: 9,
            recordingUrl: 'https://example.com/recording.mp3',
            model: 'gpt-4o-transcribe',
            fallbackModel: 'gemini-3.8-flash',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame('gpt-4o-transcribe', $result->model);
    }

    public function test_append_drops_repeated_transcript_prefix(): void
    {
        $this->assertSame(
            "کارشناس: سلام\nمشتری: ممنون",
            TranscriptionPrompt::append('کارشناس: سلام', "کارشناس: سلام\nمشتری: ممنون"),
        );
    }

    private function provider(?string $baseUrl = null): OpenAiProvider
    {
        $provider = new OpenAiProvider;
        $provider->configure(new LlmConnectionConfig(
            connectionId: 1,
            organizationId: 1,
            providerCode: LlmProviderCode::OpenAi,
            name: 'OpenAI',
            credentials: new LlmCredentials(apiKey: 'test-key', baseUrl: $baseUrl),
            settings: new LlmSettings,
            isDefault: true,
            isActive: true,
        ));

        return $provider;
    }
}
