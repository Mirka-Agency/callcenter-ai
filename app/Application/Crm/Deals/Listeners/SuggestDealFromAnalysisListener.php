<?php

namespace App\Application\Crm\Deals\Listeners;

use App\Application\Crm\Deals\Actions\SuggestDealFromAnalysis;
use App\Domain\Customer\Events\CustomerSyncedFromAnalysis;
use App\Models\ConversationAnalysis;
use Illuminate\Contracts\Queue\ShouldQueue;

class SuggestDealFromAnalysisListener implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(private SuggestDealFromAnalysis $suggestions) {}

    public function viaQueue(): string
    {
        return (string) config('queue.names.followup');
    }

    public function handle(CustomerSyncedFromAnalysis $event): void
    {
        $analysis = ConversationAnalysis::query()->find($event->analysisId);

        if (! $analysis || (int) $analysis->organization_id !== $event->organizationId) {
            return;
        }

        $this->suggestions->handle($analysis, $event->customerId);
    }
}
