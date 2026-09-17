<?php

namespace App\Application\Intelligence\Listeners;

use App\Application\Call\Services\CallIngestionService;
use App\Application\Intelligence\Services\CallAnalysisQueueService;
use App\Domain\Voip\Events\CallEnded;
use App\Domain\Voip\Events\RecordingCreated;
use App\Models\Call;
use App\Models\VoipCallLog;

class StartCallIntelligenceAnalysis
{
    public function handleVoipEvent(CallEnded|RecordingCreated $event): void
    {
        $callLog = VoipCallLog::query()
            ->where('organization_voip_connection_id', $event->connectionId)
            ->where('external_call_id', $event->event->callId)
            ->first();

        if (! $callLog) {
            return;
        }

        $callId = app(CallIngestionService::class)->ingestFromVoipLog($callLog);
        $call = Call::query()->find($callId);

        if (! $call) {
            return;
        }

        app(CallAnalysisQueueService::class)->dispatchForCall($call);
    }
}
