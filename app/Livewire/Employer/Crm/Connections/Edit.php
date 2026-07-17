<?php

namespace App\Livewire\Employer\Crm\Connections;

use App\Application\Crm\Services\CrmConnectionLifecycleService;
use App\Models\CrmProvider;
use App\Models\OrganizationCrmConnection;
use App\Services\EmployerContext;
use App\Services\EmployerIntegrationGate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('ویرایش اتصال CRM')]
class Edit extends Component
{
    public OrganizationCrmConnection $connection;

    public int $crm_provider_id = 0;

    public string $name = '';

    public bool $is_default = false;

    public bool $is_active = true;

    public string $api_url = '';

    public string $api_key = '';

    public string $api_token = '';

    public string $username = '';

    public string $password = '';

    public string $webhook_url = '';

    public string $webhook_secret = '';

    public int $timeout = 30;

    public string $pipeline_id = '';

    public string $pipeline_stage_id = '';

    public string $deal_owner_id = '';

    public function mount(OrganizationCrmConnection $connection): void
    {
        EmployerIntegrationGate::authorizeFullManagement();
        abort_unless($connection->organization_id === EmployerContext::organizationId(), 404);

        $this->connection = $connection;
        $credentials = $connection->credentials ?? [];
        $settings = $connection->settings ?? [];

        $this->crm_provider_id = (int) $connection->crm_provider_id;
        $this->name = $connection->name;
        $this->is_default = (bool) $connection->is_default;
        $this->is_active = (bool) $connection->is_active;
        $this->api_url = (string) ($credentials['api_url'] ?? '');
        $this->api_key = '';
        $this->api_token = '';
        $this->username = (string) ($credentials['username'] ?? '');
        $this->password = '';
        $this->webhook_url = (string) ($settings['webhook_url'] ?? '');
        $this->webhook_secret = '';
        $this->timeout = (int) ($settings['timeout'] ?? 30);
        $this->pipeline_id = (string) ($settings['pipeline_id'] ?? '');
        $this->pipeline_stage_id = (string) ($settings['pipeline_stage_id'] ?? '');
        $this->deal_owner_id = (string) ($settings['deal_owner_id'] ?? '');
    }

    public function save(): void
    {
        EmployerIntegrationGate::authorizeFullManagement();

        $data = $this->validate([
            'crm_provider_id' => ['required', 'exists:crm_providers,id'],
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'api_url' => ['required', 'url'],
            'api_key' => ['nullable', 'string'],
            'api_token' => ['nullable', 'string'],
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'webhook_url' => ['nullable', 'url'],
            'webhook_secret' => ['nullable', 'string'],
            'timeout' => ['integer', 'min:5', 'max:120'],
            'pipeline_id' => ['nullable', 'string', 'max:100'],
            'pipeline_stage_id' => ['nullable', 'string', 'max:100'],
            'deal_owner_id' => ['nullable', 'string', 'max:100'],
        ]);

        app(CrmConnectionLifecycleService::class)->update($this->connection, [
            'crm_provider_id' => $data['crm_provider_id'],
            'name' => $data['name'],
            'is_default' => $data['is_default'],
            'is_active' => $data['is_active'],
            'credentials' => [
                'api_url' => $data['api_url'],
                'api_key' => $data['api_key'] ?: null,
                'api_token' => $data['api_token'] ?: null,
                'username' => $data['username'] ?: null,
                'password' => $data['password'] ?: null,
            ],
            'settings' => [
                'webhook_url' => $data['webhook_url'] ?: null,
                'webhook_secret' => $data['webhook_secret'] ?: null,
                'timeout' => $data['timeout'],
                'pipeline_id' => $data['pipeline_id'] ?: null,
                'pipeline_stage_id' => $data['pipeline_stage_id'] ?: null,
                'deal_owner_id' => $data['deal_owner_id'] ?: null,
            ],
        ]);

        session()->flash('status', __('ui.integrations.crm_saved'));

        $this->redirect(route('employer.crm.connections.index'), navigate: true);
    }

    public function test(): void
    {
        EmployerIntegrationGate::authorizeFullManagement();

        $result = app(CrmConnectionLifecycleService::class)->test($this->connection->fresh());

        $this->dispatch('show-toast', type: $result->success ? 'success' : 'error', message: $result->message ?? $result->error ?? '');
    }

    public function sync(): void
    {
        EmployerIntegrationGate::authorizeFullManagement();

        app(CrmConnectionLifecycleService::class)->queueSync($this->connection);

        session()->flash('status', __('ui.integrations.crm_sync_queued'));
    }

    public function render()
    {
        return view('livewire.employer.crm.connections.form', [
            'connection' => $this->connection,
            'providers' => CrmProvider::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
