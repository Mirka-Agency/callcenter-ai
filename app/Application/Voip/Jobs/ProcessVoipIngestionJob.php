<?php

namespace App\Application\Voip\Jobs;

use App\Application\Voip\Services\VoipConnectionResolver;
use App\Application\Voip\Services\VoipEventIngestionService;
use App\Domain\Voip\DTOs\NormalizedWebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessVoipIngestionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $forceReplay = false;

    /** @param array<string, mixed> $normalizedEvent */
    public function __construct(
        public int $connectionId,
        public array $normalizedEvent,
        bool $forceReplay = false,
    ) {
        $this->forceReplay = $forceReplay;
        $this->onQueue((string) config('voip.queue', 'default'));
    }

    public function handle(
        VoipConnectionResolver $resolver,
        VoipEventIngestionService $ingestion,
    ): void {
        [$config] = $resolver->resolveByConnectionId($this->connectionId);

        $ingestion->ingest(
            config: $config,
            event: NormalizedWebhookEvent::fromArray($this->normalizedEvent),
            rawPayload: $this->normalizedEvent['raw_payload'] ?? null,
            forceReplay: $this->forceReplay,
        );
    }
}
