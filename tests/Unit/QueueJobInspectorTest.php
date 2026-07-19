<?php

namespace Tests\Unit;

use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Services\QueueMonitoring\QueueJobInspector;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueJobInspectorTest extends TestCase
{
    public function test_inspects_analyze_audio_job_payload(): void
    {
        $recordingUrl = 'https://example.com/recording.wav';
        $payload = json_encode([
            'uuid' => (string) Str::uuid(),
            'displayName' => AnalyzeAudioJob::class,
            'maxTries' => 5,
            'timeout' => 600,
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => [
                'commandName' => AnalyzeAudioJob::class,
                'command' => serialize(new AnalyzeAudioJob(42, $recordingUrl)),
            ],
        ]);

        $inspection = app(QueueJobInspector::class)->inspect(
            $payload,
            "RuntimeException: API returned 503\n#0 /app/AnalyzeAudioJob.php(120)",
        );

        $this->assertSame('AnalyzeAudioJob', $inspection->jobClass);
        $this->assertSame(42, $inspection->callId());
        $this->assertSame($recordingUrl, $inspection->properties['recordingUrl']);
        $this->assertSame(5, $inspection->maxTries);
        $this->assertSame(600, $inspection->timeout);
        $this->assertSame('RuntimeException: API returned 503', $inspection->exceptionMessage);
        $this->assertStringContainsString('503', $inspection->exceptionFull ?? '');
    }

    public function test_returns_empty_inspection_for_invalid_payload(): void
    {
        $inspection = app(QueueJobInspector::class)->inspect('not-json');

        $this->assertSame([], $inspection->properties);
        $this->assertNull($inspection->callId());
        $this->assertSame(__('filament.misc.unknown_job'), $inspection->shortLabel());
    }

    public function test_inspect_tolerates_legacy_jobs_missing_newer_properties(): void
    {
        $reflection = new \ReflectionClass(\App\Application\Voip\Jobs\ProcessVoipWebhookJob::class);
        $legacyJob = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('connectionId')->setValue($legacyJob, 1);
        $reflection->getProperty('payload')->setValue($legacyJob, ['event_name' => 'Cdr']);

        $payload = json_encode([
            'uuid' => (string) Str::uuid(),
            'displayName' => \App\Application\Voip\Jobs\ProcessVoipWebhookJob::class,
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => [
                'commandName' => \App\Application\Voip\Jobs\ProcessVoipWebhookJob::class,
                'command' => serialize($legacyJob),
            ],
        ]);

        $inspection = app(QueueJobInspector::class)->inspect($payload);

        $this->assertSame('ProcessVoipWebhookJob', $inspection->jobClass);
        $this->assertSame(1, $inspection->properties['connectionId'] ?? null);
        $this->assertSame(['event_name' => 'Cdr'], $inspection->properties['payload'] ?? null);
    }
}
