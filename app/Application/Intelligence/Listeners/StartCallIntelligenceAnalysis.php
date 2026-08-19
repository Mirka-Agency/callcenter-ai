<?php

namespace App\Application\Intelligence\Listeners;

use App\Application\Call\Services\CallIngestionService;
use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Domain\Voip\Events\CallEnded;
use App\Domain\Voip\Events\RecordingCreated;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Call;
use App\Models\VoipCallLog;
use App\Services\AiBillingService;

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

        if (! $call?->organization_user_id) {
            return;
        }

        if ($callLog->recording_url || $event instanceof RecordingCreated) {
            try {
                app(AiBillingService::class)->assertCanAnalyze($callLog->organization_id);
            } catch (InsufficientWalletBalanceException) {
                return;
            }

            AnalyzeAudioJob::dispatchChain($callId, $callLog->recording_url);
        }
    }
}
