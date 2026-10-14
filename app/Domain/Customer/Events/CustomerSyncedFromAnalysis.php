<?php

namespace App\Domain\Customer\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerSyncedFromAnalysis
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $organizationId,
        public int $analysisId,
        public int $customerId,
    ) {}
}
