<?php

namespace App\Application\Crm\Tasks\Listeners;

use App\Application\Crm\Tasks\Actions\SuggestTasksFromAnalysis;
use App\Domain\Customer\Events\CustomerSyncedFromAnalysis;
use App\Models\ConversationAnalysis;
use Illuminate\Contracts\Queue\ShouldQueue;

class SuggestTasksFromAnalysisListener implements ShouldQueue
{
    public string $queue = 'followup';

    public int $tries = 3;

    public function __construct(private SuggestTasksFromAnalysis $suggest) {}

    public function handle(CustomerSyncedFromAnalysis $event): void
    {
        $analysis = ConversationAnalysis::query()->find($event->analysisId);
        if (! $analysis instanceof ConversationAnalysis) {
            return;
        }

        $this->suggest->handle($analysis, $event->customerId);
    }
}
