<?php

namespace App\Domain\Crm\Tasks\Events;

readonly class TaskCreated
{
    public function __construct(
        public int $organizationId,
        public int $taskId,
        public ?int $actorUserId,
        public string $source,
    ) {}
}
