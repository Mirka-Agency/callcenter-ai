<?php

namespace App\Livewire\Employer\Voip;

use App\Domain\Voip\Enums\CallStatus;
use App\Enums\IntegrationSetupStatus;
use App\Models\VoipCallLog;
use App\Services\EmployerContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('VoIP')]
class Index extends Component
{
    public function regenerateWebhookToken(int $connectionId): void
    {
        $connection = EmployerContext::organization()
            ->voipConnections()
            ->whereKey($connectionId)
            ->firstOrFail();

        $connection->regenerateWebhookToken();

        session()->flash('status', __('ui.voip.webhook_token_regenerated'));
    }

    public function render()
    {
        $organization = EmployerContext::organization();
        $organizationId = $organization->id;
        $readiness = EmployerContext::integrationReadiness();
        $isComplete = $readiness->voipStatus === IntegrationSetupStatus::Complete;
        $connections = $isComplete
            ? $organization->voipConnections()->with('provider')->get()
            : collect();

        $providerCodes = $connections->pluck('provider.code')->filter()->unique()->values();
        $hasCustom = $providerCodes->contains('custom');
        $hasSimotelFamily = $providerCodes->contains(fn (string $code): bool => in_array($code, ['simotel', 'novatel'], true));

        $recentCallsHint = match (true) {
            $hasCustom && $hasSimotelFamily => __('ui.voip.recent_calls_hint_mixed'),
            $hasCustom => __('ui.voip.recent_calls_hint_custom'),
            default => __('ui.voip.recent_calls_hint_simotel'),
        };

        return view('livewire.employer.voip.index', [
            'connections' => $connections,
            'integrationReadiness' => $readiness->toArray(),
            'isComplete' => $isComplete,
            'recentCallsHint' => $recentCallsHint,
            'todayCalls' => $isComplete
                ? VoipCallLog::query()->where('organization_id', $organizationId)->whereDate('started_at', today())->count()
                : 0,
            'monthCalls' => $isComplete
                ? VoipCallLog::query()->where('organization_id', $organizationId)->whereMonth('started_at', now()->month)->count()
                : 0,
            'missedCalls' => $isComplete
                ? VoipCallLog::query()
                    ->where('organization_id', $organizationId)
                    ->where('status', CallStatus::Missed)
                    ->count()
                : 0,
            'recentCalls' => $isComplete
                ? VoipCallLog::query()->where('organization_id', $organizationId)->latest('started_at')->limit(10)->get()
                : collect(),
            'incomingCallEndpoint' => url('/api/voip/incoming-call'),
        ]);
    }
}
