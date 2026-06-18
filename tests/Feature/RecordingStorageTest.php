<?php

namespace Tests\Feature;

use App\Services\RecordingStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordingStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_put_verifies_file_exists_on_disk(): void
    {
        Storage::fake('local');
        config(['recordings.disk' => 'local']);

        app(RecordingStorage::class)->put('recordings/1/test.mp3', 'audio-bytes', 'audio/mpeg');

        Storage::disk('local')->assertExists('recordings/1/test.mp3');
    }

    public function test_read_for_analysis_falls_back_to_configured_disk(): void
    {
        Storage::fake('local');
        config(['recordings.disk' => 'local']);

        $path = 'recordings/2/sample.mp3';
        Storage::disk('local')->put($path, 'audio-bytes');

        $payload = app(RecordingStorage::class)->readForAnalysis($path, 's3');

        $this->assertSame('audio-bytes', $payload['content']);
        $this->assertSame('mp3', $payload['format']);
        $this->assertSame('local', $payload['disk']);
    }
}
