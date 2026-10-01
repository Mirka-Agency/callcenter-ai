<?php

namespace App\Application\Voip\Jobs;

use App\Application\Voip\VoipManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncVoipExtensionsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 300;

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function uniqueId(): string
    {
        return $this->organizationId.'-'.($this->connectionId ?? 'all');
    }

    public function __construct(
        public int $organizationId,
        public ?int $connectionId = null,
    ) {
        $this->onQueue((string) config('queue.names.followup'));
    }

    public function handle(): void
    {
        $manager = VoipManager::forOrganization($this->organizationId);

        if ($this->connectionId) {
            $manager->connection($this->connectionId);
        }

        $manager->getExtensions();
    }
}
