<?php

namespace App\Livewire\Employer\Crm\Connections;

use App\Application\Crm\Services\CrmConnectionLifecycleService;
use App\Models\OrganizationCrmConnection;
use App\Services\EmployerContext;
use App\Services\EmployerIntegrationGate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('مدیریت اتصالات CRM')]
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
        app(CrmConnectionLifecycleService::class)->delete($connection);

        session()->flash('status', __('ui.integrations.crm_deleted'));
    }

    public function test(int $connectionId): void
    {
        EmployerIntegrationGate::authorizeFullManagement();

        $connection = $this->findConnection($connectionId);
        $result = app(CrmConnectionLifecycleService::class)->test($connection);

        $this->dispatch('show-toast', type: $result->success ? 'success' : 'error', message: $result->message ?? $result->error ?? '');
    }

    public function sync(int $connectionId): void
    {
        EmployerIntegrationGate::authorizeFullManagement();

        $connection = $this->findConnection($connectionId);
        app(CrmConnectionLifecycleService::class)->queueSync($connection);

        session()->flash('status', __('ui.integrations.crm_sync_queued'));
    }

    public function render()
    {
        return view('livewire.employer.crm.connections.index', [
            'connections' => EmployerContext::organization()
                ->crmConnections()
                ->with('provider')
                ->orderByDesc('is_default')
                ->get(),
        ]);
    }

    private function findConnection(int $connectionId): OrganizationCrmConnection
    {
        return EmployerContext::organization()
            ->crmConnections()
            ->whereKey($connectionId)
            ->firstOrFail();
    }
}
