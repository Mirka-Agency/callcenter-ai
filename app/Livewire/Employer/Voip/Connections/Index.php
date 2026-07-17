<?php

namespace App\Livewire\Employer\Voip\Connections;

use App\Application\Voip\Services\VoipConnectionLifecycleService;
use App\Models\OrganizationVoipConnection;
use App\Services\EmployerContext;
use App\Services\EmployerIntegrationGate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('مدیریت اتصالات VoIP')]
class Index extends Component
{
    public function mount(): void
    {
        EmployerIntegrationGate::authorizeFullManagement();
    }

    public function delete(int $connectionId): void
    {
        EmployerIntegrationGate::authorizeFullManagement();

        $connection = $this->findConnection($connectionId);
        app(VoipConnectionLifecycleService::class)->delete($connection);

        session()->flash('status', __('ui.integrations.voip_deleted'));
    }

    public function test(int $connectionId): void
    {
        EmployerIntegrationGate::authorizeFullManagement();

        $connection = $this->findConnection($connectionId);
        $result = app(VoipConnectionLifecycleService::class)->test($connection);

        $this->dispatch('show-toast', type: $result->success ? 'success' : 'error', message: $result->message ?? $result->error ?? '');
    }

    public function sync(int $connectionId): void
    {
        EmployerIntegrationGate::authorizeFullManagement();

        $connection = $this->findConnection($connectionId);
        app(VoipConnectionLifecycleService::class)->queueSyncExtensions($connection);

        session()->flash('status', __('ui.integrations.voip_sync_queued'));
    }

    public function regenerateToken(int $connectionId): void
    {
        EmployerIntegrationGate::authorizeFullManagement();

        $connection = $this->findConnection($connectionId);
        app(VoipConnectionLifecycleService::class)->regenerateWebhookToken($connection);

        session()->flash('status', __('ui.voip.webhook_token_regenerated'));
    }

    public function render()
    {
        return view('livewire.employer.voip.connections.index', [
            'connections' => EmployerContext::organization()
                ->voipConnections()
                ->with('provider')
                ->orderByDesc('is_default')
                ->get(),
        ]);
    }

    private function findConnection(int $connectionId): OrganizationVoipConnection
    {
        return EmployerContext::organization()
            ->voipConnections()
            ->whereKey($connectionId)
            ->firstOrFail();
    }
}
