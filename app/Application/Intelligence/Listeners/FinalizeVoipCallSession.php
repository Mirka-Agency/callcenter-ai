<?php

namespace App\Application\Intelligence\Listeners;

use App\Application\Call\Services\CallIngestionService;
use App\Domain\Call\Enums\IncomingCallStatus;
use App\Domain\Voip\Events\CallEnded;
use App\Domain\Voip\Events\CallMissed;
use App\Models\IncomingCallSession;
use App\Models\VoipCallLog;

class FinalizeVoipCallSession
{
    public function handle(CallEnded|CallMissed $event): void
    {
        $callLog = null;

        if ($event->event->callId) {
            $callLog = VoipCallLog::query()
                ->where('organization_voip_connection_id', $event->connectionId)
                ->where('external_call_id', $event->event->callId)
                ->first();
        }

        if ($callLog && $event instanceof CallMissed) {
            app(CallIngestionService::class)->ingestFromVoipLog($callLog);
        }

        $sessionStatus = $event instanceof CallMissed
            ? IncomingCallStatus::Missed
            : IncomingCallStatus::Completed;

        $query = IncomingCallSession::query()
            ->where('organization_id', $event->organizationId)
            ->where('status', IncomingCallStatus::Ringing);

        if ($event->event->callId) {
            $query->where(function ($inner) use ($event, $callLog): void {
                $inner->where('external_call_id', $event->event->callId);

                if ($callLog) {
                    $inner->orWhere('voip_call_log_id', $callLog->id);
                }

                if ($event->event->sourceNumber) {
                    $inner->orWhere('caller_number', $event->event->sourceNumber);
                }
            });
        } elseif ($event->event->sourceNumber) {
            $query->where('caller_number', $event->event->sourceNumber);
        } else {
            return;
        }

        $query->update([
            'status' => $sessionStatus,
            'voip_call_log_id' => $callLog?->id,
            'updated_at' => now(),
        ]);
    }
}
