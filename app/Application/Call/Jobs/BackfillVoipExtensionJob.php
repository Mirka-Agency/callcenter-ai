<?php

namespace App\Application\Call\Jobs;

use App\Application\Call\Services\UnmatchedVoipExtensionService;
use App\Models\Organization;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BackfillVoipExtensionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $organizationId,
        public string $extension,
        public int $connectionId,
        public ?int $days = null,
        public ?int $organizationUserId = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->organizationId.'-'.$this->connectionId.'-'.$this->extension;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(UnmatchedVoipExtensionService $extensions): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if (! $organization) {
            return;
        }

        $extensions->backfillCalls(
            organization: $organization,
            extension: $this->extension,
            connectionId: $this->connectionId,
            days: $this->days,
            organizationUserId: $this->organizationUserId,
        );
    }
}
