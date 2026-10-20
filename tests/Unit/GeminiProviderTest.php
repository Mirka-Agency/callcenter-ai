<?php

namespace Tests\Unit;

use App\Application\Llm\Services\AudioSegmenter;
use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\DTOs\LlmConnectionConfig;
use App\Domain\Llm\DTOs\LlmCredentials;
use App\Domain\Llm\DTOs\LlmSettings;
use App\Domain\Llm\DTOs\TranscriptionRequestData;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Infrastructure\Llm\Adapters\GeminiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_audio_sends_inline_audio_to_gemini_generate_content(): void
    {
        $analysisJson = json_encode([
            'score' => 82,
            'summary' => 'خلاصه تماس به فارسی',
            'sentiment' => 'positive',
            'strengths' => ['همدلی خوب'],
            'weaknesses' => [],
            'next_actions' => ['پیگیری'],
        ], JSON_UNESCAPED_UNICODE);

        Http::fake([
            'https://example.com/recording.mp3' => Http::response('audio-bytes'),
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => $analysisJson]],
                    ],
                ]],
                'usageMetadata' => [
                    'promptTokenCount' => 1200,
                    'candidatesTokenCount' => 300,
                    'thoughtsTokenCount' => 80,
                ],
            ]),
        ]);

        $provider = new GeminiProvider;
        $provider->configure(new LlmConnectionConfig(
            connectionId: null,
            organizationId: 1,
            providerCode: LlmProviderCode::Gemini,
            name: 'Gemini',
            credentials: new LlmCredentials(apiKey: 'test-gemini-key'),
            settings: new LlmSettings(temperature: 0.2, maxOutputTokens: 1500),
            isDefault: true,
            isActive: true,
        ));

        $result = $provider->analyzeAudio(new AudioAnalysisRequestData(
            callId: 99,
            recordingUrl: 'https://example.com/recording.mp3',
            model: 'gemini-3.8-flash',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(82, $result->data['score']);
        $this->assertSame(1200, $result->inputTokens);
        $this->assertSame(380, $result->outputTokens);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'generativelanguage.googleapis.com')) {
                return false;
            }

            $body = $request->data();
            $parts = $body['contents'][0]['parts'] ?? [];
            $generationConfig = $body['generationConfig'] ?? [];

            return str_contains($request->url(), 'models/gemini-3.8-flash:generateContent')
                && str_contains($request->url(), 'key=test-gemini-key')
                && ($generationConfig['responseMimeType'] ?? null) === 'application/json'
                && ($generationConfig['thinkingConfig']['thinkingLevel'] ?? null) === 'medium'
                && ! array_key_exists('temperature', $generationConfig)
                && ($parts[1]['inline_data']['mime_type'] ?? null) === 'audio/mpeg'
                && ($parts[1]['inline_data']['data'] ?? null) === base64_encode('audio-bytes');
        });
        Http::assertSentCount(3);
    }

    public function test_english_analysis_is_accepted_without_a_second_model_request(): void
    {
        $analysisJson = json_encode([
            'score' => 70,
            'summary' => 'The customer asked about pricing and next steps.',
            'sentiment' => 'neutral',
            'strengths' => ['Clear explanation'],
            'weaknesses' => [],
            'next_actions' => ['Follow up tomorrow'],
        ]);

        Http::fake([
            'https://example.com/recording.mp3' => Http::response('audio-bytes'),
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => $analysisJson]],
                    ],
                ]],
                'usageMetadata' => [
                    'promptTokenCount' => 1200,
                    'candidatesTokenCount' => 300,
                ],
            ]),
        ]);

        $provider = new GeminiProvider;
        $provider->configure(new LlmConnectionConfig(
            connectionId: null,
            organizationId: 1,
            providerCode: LlmProviderCode::Gemini,
            name: 'Gemini',
            credentials: new LlmCredentials(apiKey: 'test-gemini-key'),
            settings: new LlmSettings,
            isDefault: true,
            isActive: true,
        ));

        $result = $provider->analyzeAudio(new AudioAnalysisRequestData(
            callId: 99,
            recordingUrl: 'https://example.com/recording.mp3',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame('The customer asked about pricing and next steps.', $result->data['summary']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'models/gemini-3.8-flash:generateContent');
        });
        Http::assertSentCount(3);
    }

    public function test_analyze_audio_without_api_key_and_audio_refuses_real_analysis(): void
    {
        $provider = new GeminiProvider;
        $provider->configure(new LlmConnectionConfig(
            connectionId: null,
            organizationId: 1,
            providerCode: LlmProviderCode::Gemini,
            name: 'Gemini',
            credentials: new LlmCredentials(apiKey: null),
            settings: new LlmSettings,
            isDefault: true,
            isActive: true,
        ));

        $result = $provider->analyzeAudio(new AudioAnalysisRequestData(
            callId: 1,
            recordingUrl: 'https://example.com/recording.mp3',
            sendAudioFile: true,
        ));

        $this->assertFalse($result->success);
        $this->assertStringContainsString('کلید API', $result->error ?? '');
    }

    public function test_transcribe_continues_until_the_full_transcript_is_returned(): void
    {
        $attempt = 0;

        Http::fake(function ($request) use (&$attempt) {
            if (str_contains($request->url(), 'example.com')) {
                return Http::response('audio-bytes');
            }

            $attempt++;
            $body = $request->data();
            $config = $body['generationConfig'] ?? [];

            $this->assertSame('text/plain', $config['responseMimeType'] ?? null);
            $this->assertSame('minimal', $config['thinkingConfig']['thinkingLevel'] ?? null);

            if ($attempt === 1) {
                return Http::response([
                    'candidates' => [[
                        'finishReason' => 'MAX_TOKENS',
                        'content' => ['parts' => [['text' => 'کارشناس: سلام، وقت بخیر']]],
                    ]],
                    'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 4],
                ]);
            }

            $userText = $body['contents'][0]['parts'][0]['text'] ?? '';
            $this->assertStringContainsString('کارشناس: سلام، وقت بخیر', $userText);

            return Http::response([
                'candidates' => [[
                    'finishReason' => 'STOP',
                    'content' => ['parts' => [['text' => 'مشتری: سفارشم نرسیده است']]],
                ]],
                'usageMetadata' => ['promptTokenCount' => 8, 'candidatesTokenCount' => 3],
            ]);
        });

        $provider = new GeminiProvider;
        $provider->configure(new LlmConnectionConfig(
            connectionId: null,
            organizationId: 1,
            providerCode: LlmProviderCode::Gemini,
            name: 'Gemini',
            credentials: new LlmCredentials(apiKey: 'test-gemini-key'),
            settings: new LlmSettings,
            isDefault: true,
            isActive: true,
        ));

        $result = $provider->transcribe(new TranscriptionRequestData(
            callId: 7,
            recordingUrl: 'https://example.com/recording.mp3',
            fallbackModel: 'gemini-3.8-flash',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(
            "کارشناس: سلام، وقت بخیر\nمشتری: سفارشم نرسیده است",
            $result->data['transcript'] ?? null,
        );
        $this->assertSame(2, $attempt);
    }

    public function test_transcribe_prefers_the_selected_model_over_the_analysis_model(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'example.com')) {
                return Http::response('audio-bytes');
            }

            $this->assertStringContainsString('/models/gemini-2.5-flash:generateContent', $request->url());

            return Http::response([
                'candidates' => [[
                    'finishReason' => 'STOP',
                    'content' => ['parts' => [['text' => 'کارشناس: سلام']]],
                ]],
            ]);
        });

        $provider = new GeminiProvider;
        $provider->configure(new LlmConnectionConfig(
            connectionId: null,
            organizationId: 1,
            providerCode: LlmProviderCode::Gemini,
            name: 'Gemini',
            credentials: new LlmCredentials(apiKey: 'test-gemini-key'),
            settings: new LlmSettings,
            isDefault: true,
            isActive: true,
        ));

        $result = $provider->transcribe(new TranscriptionRequestData(
            callId: 11,
            recordingUrl: 'https://example.com/recording.mp3',
            model: 'gemini-2.5-flash',
            fallbackModel: 'gemini-3.8-flash',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame('gemini-2.5-flash', $result->model);
    }

    public function test_analyze_audio_uses_a_cached_system_prompt_when_the_provider_stores_one(): void
    {
        $analysisJson = json_encode([
            'score' => 80,
            'summary' => 'خلاصه فارسی',
            'sentiment' => 'positive',
            'strengths' => [],
            'weaknesses' => [],
            'next_actions' => [],
        ], JSON_UNESCAPED_UNICODE);

        Http::fake(function ($request) use ($analysisJson) {
            if (str_contains($request->url(), 'example.com')) {
                return Http::response('audio-bytes');
            }

            if (str_contains($request->url(), '/cachedContents')) {
                return Http::response(['name' => 'cachedContents/call-policy']);
            }

            $body = $request->data();
            $this->assertSame('cachedContents/call-policy', $body['cachedContent'] ?? null);
            $this->assertArrayNotHasKey('system_instruction', $body);

            return Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => $analysisJson]]],
                ]],
                'usageMetadata' => [
                    'promptTokenCount' => 10000,
                    'cachedContentTokenCount' => 9000,
                    'candidatesTokenCount' => 200,
                ],
            ]);
        });

        $provider = new GeminiProvider;
        $provider->configure(new LlmConnectionConfig(
            connectionId: null,
            organizationId: 1,
            providerCode: LlmProviderCode::Gemini,
            name: 'Gemini',
            credentials: new LlmCredentials(apiKey: 'test-gemini-key'),
            settings: new LlmSettings,
            isDefault: true,
            isActive: true,
        ));

        $result = $provider->analyzeAudio(new AudioAnalysisRequestData(
            callId: 4,
            recordingUrl: 'https://example.com/recording.mp3',
            model: 'gemini-3.8-flash',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(10000, $result->inputTokens);
        $this->assertSame(9000, $result->cachedTokens);
    }

    public function test_transcribe_sends_each_segment_once_instead_of_repeating_the_whole_file(): void
    {
        $this->app->instance(AudioSegmenter::class, new class extends AudioSegmenter
        {
            public function segments(string $bytes, string $format): ?array
            {
                return ['chunk-one', 'chunk-two'];
            }
        });

        $seen = [];

        Http::fake(function ($request) use (&$seen) {
            if (str_contains($request->url(), 'example.com')) {
                return Http::response('full-audio');
            }

            $audio = $request->data()['contents'][0]['parts'][1]['inline_data']['data'] ?? '';
            $seen[] = $audio;
            $text = $audio === base64_encode('chunk-one') ? 'کارشناس: سلام' : 'مشتری: بله';

            return Http::response([
                'candidates' => [[
                    'finishReason' => 'STOP',
                    'content' => ['parts' => [['text' => $text]]],
                ]],
                'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 2],
            ]);
        });

        $provider = new GeminiProvider;
        $provider->configure(new LlmConnectionConfig(
            connectionId: null,
            organizationId: 1,
            providerCode: LlmProviderCode::Gemini,
            name: 'Gemini',
            credentials: new LlmCredentials(apiKey: 'test-gemini-key'),
            settings: new LlmSettings,
            isDefault: true,
            isActive: true,
        ));

        $result = $provider->transcribe(new TranscriptionRequestData(
            callId: 8,
            recordingUrl: 'https://example.com/recording.mp3',
            fallbackModel: 'gemini-3.8-flash',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(
            [base64_encode('chunk-one'), base64_encode('chunk-two')],
            $seen,
        );
        $this->assertSame("کارشناس: سلام\nمشتری: بله", $result->data['transcript'] ?? null);
        $this->assertSame(10, $result->inputTokens);
    }
}
