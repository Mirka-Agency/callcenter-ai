<?php

namespace Tests\Unit;

use App\Application\Llm\Services\RecordingTranscriber;
use App\Domain\Llm\DTOs\LlmConnectionConfig;
use App\Domain\Llm\DTOs\LlmCredentials;
use App\Domain\Llm\DTOs\LlmSettings;
use App\Domain\Llm\Enums\LlmProviderCode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordingTranscriberTest extends TestCase
{
    public function test_streams_the_recording_and_reuses_the_transcript(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('recordings/call.mp3', 'audio-bytes');

        Http::fake([
            'https://llm.test/v1/audio/transcriptions' => Http::response([
                'text' => 'کارشناس: سلام. مشتری: وضعیت سفارش را می‌خواستم.',
            ]),
        ]);

        $transcript = app(RecordingTranscriber::class)->transcribe(
            callId: 15,
            storagePath: 'recordings/call.mp3',
            storageDisk: 'local',
            sourceUrl: null,
            mimeType: 'audio/mpeg',
            fileSizeBytes: 11,
            config: $this->config(),
        );

        $this->assertSame('کارشناس: سلام. مشتری: وضعیت سفارش را می‌خواستم.', $transcript);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://llm.test/v1/audio/transcriptions'
                && $request->isMultipart();
        });

        $cached = app(RecordingTranscriber::class)->transcribe(
            callId: 15,
            storagePath: 'recordings/call.mp3',
            storageDisk: 'local',
            sourceUrl: null,
            mimeType: 'audio/mpeg',
            fileSizeBytes: 11,
            config: $this->config(),
        );

        $this->assertSame($transcript, $cached);
        Http::assertSentCount(1);
    }

    public function test_rejects_an_empty_transcript(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('recordings/empty.mp3', 'audio-bytes');

        Http::fake([
            'https://llm.test/v1/audio/transcriptions' => Http::response(['text' => '   ']),
        ]);

        $this->expectExceptionMessage('تبدیل گفتار به متن نتیجه‌ای نداد');

        app(RecordingTranscriber::class)->transcribe(
            callId: 16,
            storagePath: 'recordings/empty.mp3',
            storageDisk: 'local',
            sourceUrl: null,
            mimeType: 'audio/mpeg',
            fileSizeBytes: 11,
            config: $this->config(),
        );
    }

    private function config(): LlmConnectionConfig
    {
        return new LlmConnectionConfig(
            connectionId: null,
            organizationId: 1,
            providerCode: LlmProviderCode::OpenAi,
            name: 'OpenAI',
            credentials: new LlmCredentials(
                apiKey: 'test-key',
                baseUrl: 'https://llm.test/v1',
            ),
            settings: new LlmSettings(transcriptionModel: 'gpt-4o-mini-transcribe'),
            isDefault: true,
            isActive: true,
        );
    }
}
