<?php

namespace App\Domain\Crm\Deals\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DealStageChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $organizationId,
        public int $dealId,
        public ?int $actorUserId,
        public string $source,
        public ?int $fromStageId,
        public ?int $toStageId,
        public string $fromStatus,
        public string $toStatus,
    ) {}
}
