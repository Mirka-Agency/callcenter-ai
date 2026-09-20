<?php

namespace Tests\Unit;

use App\Infrastructure\Recording\HttpRecordingDownloader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HttpRecordingDownloaderTest extends TestCase
{
    public function test_retries_dated_mixmonitor_path_when_basename_url_is_missing(): void
    {
        Storage::fake('local');
        config(['recordings.disk' => 'local']);

        $basename = 'http://192.168.2.16/mirka-call-recordings/exten-116-09309194604-20260920-092247-1789879949.32755.wav';
        $dated = 'http://192.168.2.16/mirka-call-recordings/2026/09/20/exten-116-09309194604-20260920-092247-1789879949.32755.wav';

        Http::fake([
            $dated => Http::response('audio-bytes', 200, ['Content-Type' => 'audio/wav']),
            $basename => Http::response('not found', 404),
        ]);

        $result = app(HttpRecordingDownloader::class)->download($basename, 42);

        $this->assertTrue($result->success);
        $this->assertSame('audio/wav', $result->mimeType);
        $this->assertSame(strlen('audio-bytes'), $result->fileSizeBytes);
        Http::assertSent(fn ($request) => $request->url() === $dated);
    }
}
