<?php

namespace App\Application\Customer\Actions;

use App\Models\ConversationAnalysis;
use App\Models\Customer;
use App\Services\CustomerIntelligenceService;

class EnrichCustomerFromAnalysis
{
    public function __construct(
        private CustomerIntelligenceService $customers,
    ) {}

    public function handle(ConversationAnalysis $analysis): ?Customer
    {
        return $this->customers->syncFromAnalysis($analysis);
    }
}
