<?php

namespace App\Livewire\Employer\Voip;

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

        return view('livewire.employer.voip.index', [
            'connections' => $connections,
            'integrationReadiness' => $readiness->toArray(),
            'isComplete' => $isComplete,
            'todayCalls' => $isComplete
                ? VoipCallLog::query()->where('organization_id', $organizationId)->whereDate('started_at', today())->count()
                : 0,
            'monthCalls' => $isComplete
                ? VoipCallLog::query()->where('organization_id', $organizationId)->whereMonth('started_at', now()->month)->count()
                : 0,
            'recentCalls' => $isComplete
                ? VoipCallLog::query()->where('organization_id', $organizationId)->latest('started_at')->limit(10)->get()
                : collect(),
            'incomingCallEndpoint' => url('/api/voip/incoming-call'),
        ]);
    }
}
