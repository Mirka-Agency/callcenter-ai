<?php

namespace App\Application\Customer\Listeners;

use App\Domain\Llm\Events\ConversationAnalyzed;
use App\Models\ConversationAnalysis;
use App\Services\CustomerIntelligenceService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncCustomerFromAnalysis implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        private CustomerIntelligenceService $customers,
    ) {}

    public function viaQueue(): string
    {
        return (string) config('queue.names.followup');
    }

    public function handle(ConversationAnalyzed $event): void
    {
        $analysis = ConversationAnalysis::query()->find($event->analysisId);

        if (! $analysis || $analysis->is_personal) {
            return;
        }

        $this->customers->syncFromAnalysis($analysis);
    }
}
