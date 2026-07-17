<?php

namespace App\Livewire\Employer\Crm\Connections;

use App\Application\Crm\Services\CrmConnectionLifecycleService;
use App\Models\CrmProvider;
use App\Services\EmployerContext;
use App\Services\EmployerIntegrationGate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.employer')]
#[Title('افزودن اتصال CRM')]
class Create extends Component
{
    public int $crm_provider_id = 0;

    public string $name = '';

    public bool $is_default = false;

    public bool $is_active = true;

    public string $api_url = 'https://app.didar.me/api';

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

    public function mount(): void
    {
        EmployerIntegrationGate::authorizeFullManagement();

        $firstProvider = CrmProvider::query()->where('is_active', true)->value('id');
        $this->crm_provider_id = (int) ($firstProvider ?? 0);
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

        app(CrmConnectionLifecycleService::class)->create(
            organizationId: EmployerContext::organizationId(),
            data: [
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
            ],
        );

        session()->flash('status', __('ui.integrations.crm_created'));

        $this->redirect(route('employer.crm.connections.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.employer.crm.connections.form', [
            'connection' => null,
            'providers' => CrmProvider::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
