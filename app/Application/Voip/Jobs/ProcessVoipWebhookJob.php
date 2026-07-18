<?php

namespace App\Application\Voip\Jobs;

use App\Application\Voip\Services\VoipConnectionResolver;
use App\Domain\Voip\Enums\VoipEventSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessVoipWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $connectionId,
        public array $payload,
        public bool $forceReplay = false,
    ) {
        $this->onQueue((string) config('voip.queue', 'default'));
    }

    public function handle(VoipConnectionResolver $resolver): void
    {
        [$config, $adapter] = $resolver->resolveByConnectionId($this->connectionId);

        $normalized = $adapter
            ->normalizeWebhook($this->payload)
            ->withSource(VoipEventSource::Webhook, $config->providerCode->value);

        ProcessVoipIngestionJob::dispatch(
            $this->connectionId,
            $normalized->toArray(),
            $this->forceReplay,
        );
    }
}
