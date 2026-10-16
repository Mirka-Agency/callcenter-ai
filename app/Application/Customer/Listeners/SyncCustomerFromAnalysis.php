<?php

namespace App\Application\Customer\Listeners;

use App\Application\Customer\Actions\EnrichCustomerFromAnalysis;
use App\Domain\Customer\Events\CustomerSyncedFromAnalysis;
use App\Domain\Llm\Events\ConversationAnalyzed;
use App\Models\ConversationAnalysis;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncCustomerFromAnalysis implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        private EnrichCustomerFromAnalysis $enrich,
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

        $customer = $this->enrich->handle($analysis);

        if ($customer) {
            event(new CustomerSyncedFromAnalysis(
                organizationId: (int) $analysis->organization_id,
                analysisId: (int) $analysis->id,
                customerId: (int) $customer->id,
            ));
        }
    }
}
