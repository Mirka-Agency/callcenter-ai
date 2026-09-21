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
            $dated => Http::response(str_repeat('audio-bytes', 300), 200, ['Content-Type' => 'audio/wav']),
            $basename => Http::response('not found', 404),
        ]);

        $result = app(HttpRecordingDownloader::class)->download($basename, 42);

        $this->assertTrue($result->success);
        $this->assertSame('audio/wav', $result->mimeType);
        $this->assertSame(strlen(str_repeat('audio-bytes', 300)), $result->fileSizeBytes);
        Http::assertSent(fn ($request) => $request->url() === $dated);
    }

    public function test_rejects_html_directory_listing(): void
    {
        Storage::fake('local');
        config(['recordings.disk' => 'local']);

        $url = 'http://192.168.2.16/mirka-call-recordings/';

        Http::fake([
            $url => Http::response('<html><title>Index of /mirka-call-recordings</title></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = app(HttpRecordingDownloader::class)->download($url, 99);

        $this->assertFalse($result->success);
        $this->assertSame('Recording URL did not return audio.', $result->error);
    }
}
