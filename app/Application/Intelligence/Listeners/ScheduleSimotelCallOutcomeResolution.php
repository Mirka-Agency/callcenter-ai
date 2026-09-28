<?php

namespace App\Application\Intelligence\Listeners;

use App\Application\Voip\Jobs\ResolveSimotelCallOutcomeJob;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Events\CallStarted;

class ScheduleSimotelCallOutcomeResolution
{
    public function handle(CallStarted $event): void
    {
        if (($event->event->provider ?? '') !== VoipProviderCode::Simotel->value) {
            return;
        }

        if (blank($event->event->callId)) {
            return;
        }

        // The sync driver runs delayed jobs immediately and would call Simotel
        // before the call has a disposition. The scheduled resolver covers that case.
        if (config('queue.default') === 'sync') {
            return;
        }

        $delaySeconds = max(30, (int) config('voip.simotel_outcome_resolve_delay_seconds', 90));

        ResolveSimotelCallOutcomeJob::dispatch(
            $event->organizationId,
            $event->connectionId,
            $event->event->callId,
        )->delay(now()->addSeconds($delaySeconds));
    }
}
