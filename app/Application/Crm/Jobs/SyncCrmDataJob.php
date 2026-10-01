<?php

namespace App\Application\Crm\Jobs;

use App\Application\Crm\CrmManager;
use App\Domain\Crm\DTOs\SyncData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCrmDataJob implements ShouldBeUnique, ShouldQueue
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
        return $this->organizationId.'-'.($this->connectionId ?? 'all').'-'.md5((string) json_encode($this->syncData));
    }

    public function __construct(
        public int $organizationId,
        public ?int $connectionId = null,
        public array $syncData = [],
    ) {
        $this->onQueue((string) config('queue.names.followup'));
    }

    public function handle(CrmManager $crmManager): void
    {
        $manager = CrmManager::forOrganization($this->organizationId);

        if ($this->connectionId) {
            $manager->connection($this->connectionId);
        }

        $manager->sync(SyncData::fromArray($this->syncData));
    }
}
