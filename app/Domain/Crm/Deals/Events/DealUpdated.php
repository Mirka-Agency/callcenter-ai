<?php

namespace App\Domain\Crm\Deals\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DealUpdated
{
    use Dispatchable, SerializesModels;

    /** @param  array<string, mixed>  $previous @param  array<string, mixed>  $next */
    public function __construct(
        public int $organizationId,
        public int $dealId,
        public ?int $actorUserId,
        public string $source,
        public array $previous,
        public array $next,
    ) {}
}
