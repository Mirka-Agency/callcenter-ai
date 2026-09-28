<?php

namespace App\Application\Intelligence\Jobs;

use App\Application\Crm\Services\CrmIntelligenceSyncService;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCrmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function __construct(public int $callId) {}

    public function handle(CrmIntelligenceSyncService $crmSync): void
    {
        $call = Call::query()->find($this->callId);

        if (! $call) {
            return;
        }

        $analysis = ConversationAnalysis::query()
            ->where('call_id', $call->id)
            ->latest('analyzed_at')
            ->first();

        if (! $analysis) {
            return;
        }

        $crmSync->syncAnalysis($analysis);
    }
}
