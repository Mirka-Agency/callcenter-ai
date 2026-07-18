<?php

namespace App\Livewire\Employer\Voip\Connections;

use App\Application\Voip\Services\VoipConnectionLifecycleService;
use App\Models\OrganizationVoipConnection;
use App\Services\EmployerContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.employer')]
#[Title('ویرایش اتصال VoIP')]
class Edit extends VoipConnectionForm
{
    public OrganizationVoipConnection $connection;

    public function mount(OrganizationVoipConnection $connection): void
    {
        $this->ensureAuthorized();
        abort_unless($connection->organization_id === EmployerContext::organizationId(), 404);

        $this->connection = $connection;
        $credentials = $connection->credentials ?? [];
        $settings = $connection->settings ?? [];

        $this->voip_provider_id = (int) $connection->voip_provider_id;
        $this->name = $connection->name;
        $this->is_default = (bool) $connection->is_default;
        $this->is_active = (bool) $connection->is_active;
        $this->webhook_token = '';
        $this->api_url = (string) ($credentials['api_url'] ?? '');
        $this->api_key = '';
        $this->api_token = '';
        $this->username = (string) ($credentials['username'] ?? '');
        $this->password = '';
        $this->timeout = (int) ($settings['timeout'] ?? 30);
        $this->simotel_context = (string) ($settings['extra']['context'] ?? '');
        $this->webhook_field_mapping_json = $this->encodeJsonObject($settings['webhook_field_mapping'] ?? []);
        $this->extension_mapping_json = $this->encodeJsonObject($settings['extension_mapping'] ?? []);
        $this->recording_settings_json = $this->encodeJsonObject($settings['recording_settings'] ?? []);
    }

    public function save(): void
    {
        $this->ensureAuthorized();

        $data = $this->validate($this->validationRules(creating: false));

        app(VoipConnectionLifecycleService::class)->update(
            $this->connection,
            $this->buildPayload($data),
        );

        session()->flash('status', __('ui.integrations.voip_saved'));

        $this->redirect(route('employer.voip.connections.index'), navigate: true);
    }

    public function test(): void
    {
        $this->ensureAuthorized();

        $result = app(VoipConnectionLifecycleService::class)->test($this->connection->fresh());

        $this->dispatch('show-toast', type: $result->success ? 'success' : 'error', message: $result->message ?? $result->error ?? '');
    }

    public function sync(): void
    {
        $this->ensureAuthorized();

        app(VoipConnectionLifecycleService::class)->queueSyncExtensions($this->connection);

        session()->flash('status', __('ui.integrations.voip_sync_queued'));
    }

    public function regenerateToken(): void
    {
        $this->ensureAuthorized();

        app(VoipConnectionLifecycleService::class)->regenerateWebhookToken($this->connection->fresh());

        session()->flash('status', __('ui.voip.webhook_token_regenerated'));
    }

    public function render()
    {
        return view('livewire.employer.voip.connections.form', [
            'connection' => $this->connection,
            'providers' => $this->providers(),
        ]);
    }
}
