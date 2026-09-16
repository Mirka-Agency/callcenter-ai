<?php

namespace Tests\Unit;

use App\Domain\Llm\DTOs\AudioAnalysisRequestData;
use App\Domain\Llm\DTOs\LlmConnectionConfig;
use App\Domain\Llm\DTOs\LlmCredentials;
use App\Domain\Llm\DTOs\LlmSettings;
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
            model: 'gemini-2.0-flash',
            sendAudioFile: true,
            mimeType: 'audio/mpeg',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(82, $result->data['score']);
        $this->assertSame(1200, $result->inputTokens);
        $this->assertSame(300, $result->outputTokens);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'generativelanguage.googleapis.com')) {
                return false;
            }

            $body = $request->data();
            $parts = $body['contents'][0]['parts'] ?? [];

            return str_contains($request->url(), 'key=test-gemini-key')
                && ($body['generationConfig']['responseMimeType'] ?? null) === 'application/json'
                && ($parts[1]['inline_data']['mime_type'] ?? null) === 'audio/mpeg'
                && ($parts[1]['inline_data']['data'] ?? null) === base64_encode('audio-bytes');
        });
        Http::assertSentCount(2);
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
            settings: new LlmSettings(),
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
        Http::assertSentCount(2);
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
            settings: new LlmSettings(),
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
}
