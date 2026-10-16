<?php

namespace App\Domain\Crm\Deals\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DealWon
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $organizationId,
        public int $dealId,
        public ?int $actorUserId,
        public string $source,
    ) {}
}
