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

    public function test_omitted_model_uses_the_configured_transcription_model_not_a_hardcoded_one(): void
    {
        config(['llm.transcription.model' => 'whisper-large-v3']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'example.com')) {
                return Http::response('audio-bytes');
            }

            $this->assertStringContainsString('/audio/transcriptions', $request->url());
            $body = $request->body();
            $this->assertStringContainsString('whisper-large-v3', $body);
            $this->assertStringNotContainsString('gpt-4o-transcribe', $body);
            $this->assertStringNotContainsString('gpt-4o"', $body);
            $this->assertStringContainsString('verbose_json', $body);
            $this->assertStringNotContainsString('name="prompt"', $body);

            return Http::response([
                'text' => "کارشناس: سلام، شرکت آوایار هستم\nمشتری: سفارشم نرسیده",
                'segments' => [
                    ['text' => 'کارشناس: سلام، شرکت آوایار هستم'],
                    ['text' => 'مشتری: سفارشم نرسیده'],
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
            "کارشناس: سلام، شرکت آوایار هستم\nمشتری: سفارشم نرسیده",
            $result->data['transcript'] ?? null,
        );
        $this->assertSame('whisper-large-v3', $result->model);
    }

    public function test_gateway_does_not_replace_the_selected_model_with_gpt(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'example.com/recording.mp3')) {
                return Http::response('audio-bytes');
            }

            $body = $request->body();
            $this->assertStringContainsString('/audio/transcriptions', $request->url());
            $this->assertStringContainsString('gemini-2.5-flash', $body);
            $this->assertStringNotContainsString('gpt-4o-transcribe', $body);
            $this->assertStringNotContainsString('gemini-3.8-flash', $body);

            return Http::response([
                'text' => "کارشناس: سلام\nمشتری: ممنون",
            ]);
        });

        $provider = $this->provider('https://gateway.test/v1', 'gemini-2.5-flash');

        $result = $provider->transcribe(new TranscriptionRequestData(
            callId: 8,
            recordingUrl: 'https://example.com/recording.mp3',
            fallbackModel: 'gemini-3.8-flash',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame("کارشناس: سلام\nمشتری: ممنون", $result->data['transcript'] ?? null);
        $this->assertSame('gemini-2.5-flash', $result->model);
    }

    public function test_whisper_keeps_verbose_json_and_does_not_receive_an_instruction_prompt(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'example.com')) {
                return Http::response('audio-bytes');
            }

            $body = $request->body();
            $this->assertStringContainsString('whisper-1', $body);
            $this->assertStringContainsString('verbose_json', $body);
            $this->assertStringNotContainsString('name="prompt"', $body);

            return Http::response([
                'text' => 'سلام',
                'segments' => [
                    ['text' => 'سلام'],
                ],
            ]);
        });

        $result = $this->provider()->transcribe(new TranscriptionRequestData(
            callId: 11,
            recordingUrl: 'https://example.com/recording.mp3',
            model: 'whisper-1',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame('سلام', $result->data['transcript'] ?? null);
    }

    public function test_ring_and_hold_music_are_removed_from_the_transcript(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'example.com')) {
                return Http::response('audio-bytes');
            }

            return Http::response([
                'text' => implode("\n", [
                    'کارشناس: سلام، شرکت آوایار هستم.',
                    '[موسیقی انتظار]',
                    'نامشخص: بوق بوق',
                    'مشتری: لطفاً من را به داخلی فروش وصل کنید.',
                    'نامشخص: این جمله تکراری از روی آهنگ انتظار ساخته شده است',
                    'نامشخص: این جمله تکراری از روی آهنگ انتظار ساخته شده است',
                    'نامشخص: این جمله تکراری از روی آهنگ انتظار ساخته شده است',
                    'کارشناس: وصل شدید، همکارم پاسخ می‌دهد.',
                    'مشتری: آهنگ تبلیغ را شنیدم و زنگ زدم.',
                ]),
            ]);
        });

        $result = $this->provider()->transcribe(new TranscriptionRequestData(
            callId: 12,
            recordingUrl: 'https://example.com/recording.mp3',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(
            implode("\n", [
                'کارشناس: سلام، شرکت آوایار هستم.',
                'مشتری: لطفاً من را به داخلی فروش وصل کنید.',
                'کارشناس: وصل شدید، همکارم پاسخ می‌دهد.',
                'مشتری: آهنگ تبلیغ را شنیدم و زنگ زدم.',
            ]),
            $result->data['transcript'] ?? null,
        );
    }

    public function test_whisper_drops_ringtone_and_repetitive_music_segments(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'example.com')) {
                return Http::response('audio-bytes');
            }

            $this->assertStringNotContainsString('name="prompt"', $request->body());

            return Http::response([
                'text' => 'نباید از این متن کامل استفاده شود',
                'segments' => [
                    ['text' => 'سلام، شرکت آوایار هستم', 'no_speech_prob' => 0.02, 'avg_logprob' => -0.2, 'compression_ratio' => 1.1],
                    ['text' => 'شعر ساخته‌شده از موسیقی انتظار', 'no_speech_prob' => 0.93, 'avg_logprob' => -0.4, 'compression_ratio' => 1.2],
                    ['text' => 'جمله تکراری روی آهنگ', 'no_speech_prob' => 0.1, 'avg_logprob' => -0.3, 'compression_ratio' => 2.8],
                    ['text' => 'لطفاً پیگیری کنید', 'no_speech_prob' => 0.08, 'avg_logprob' => -0.35, 'compression_ratio' => 1.3],
                ],
            ]);
        });

        $result = $this->provider()->transcribe(new TranscriptionRequestData(
            callId: 13,
            recordingUrl: 'https://example.com/recording.mp3',
            model: 'whisper-1',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(
            "سلام، شرکت آوایار هستم\nلطفاً پیگیری کنید",
            $result->data['transcript'] ?? null,
        );
        $this->assertCount(2, $result->data['speakers'] ?? []);
    }

    public function test_transcription_retries_without_logprobs_when_the_provider_rejects_them(): void
    {
        $attempts = 0;

        Http::fake(function ($request) use (&$attempts) {
            if (str_contains($request->url(), 'example.com')) {
                return Http::response('audio-bytes');
            }

            $attempts++;

            if ($attempts === 1) {
                $this->assertStringContainsString('name="include[]"', $request->body());

                return Http::response(['error' => ['message' => 'Unknown parameter: include']], 400);
            }

            $this->assertStringNotContainsString('name="include[]"', $request->body());

            return Http::response(['text' => 'کارشناس: سلام']);
        });

        $result = $this->provider()->transcribe(new TranscriptionRequestData(
            callId: 14,
            recordingUrl: 'https://example.com/recording.mp3',
            model: 'gpt-4o-transcribe',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(2, $attempts);
        $this->assertSame('کارشناس: سلام', $result->data['transcript'] ?? null);
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

    private function provider(?string $baseUrl = null, ?string $transcriptionModel = null): OpenAiProvider
    {
        $provider = new OpenAiProvider;
        $provider->configure(new LlmConnectionConfig(
            connectionId: 1,
            organizationId: 1,
            providerCode: LlmProviderCode::OpenAi,
            name: 'OpenAI',
            credentials: new LlmCredentials(apiKey: 'test-key', baseUrl: $baseUrl),
            settings: new LlmSettings(transcriptionModel: $transcriptionModel),
            isDefault: true,
            isActive: true,
        ));

        return $provider;
    }
}
