<?php

namespace App\Livewire\Employer\Voip\Connections;

use App\Domain\Voip\Enums\VoipProviderCode;
use App\Models\VoipProvider;
use App\Services\EmployerContext;
use App\Services\EmployerIntegrationGate;
use Livewire\Attributes\Computed;
use Livewire\Component;

abstract class VoipConnectionForm extends Component
{
    public int $voip_provider_id = 0;

    public string $name = '';

    public bool $is_default = false;

    public bool $is_active = true;

    public string $api_url = '';

    public string $api_key = '';

    public string $api_token = '';

    public string $username = '';

    public string $password = '';

    public string $webhook_token = '';

    public int $timeout = 30;

    public string $webhook_field_mapping_json = '{}';

    public string $extension_mapping_json = '{}';

    public string $recording_settings_json = '{}';

    public string $simotel_context = '';

    public string $ingestion_mode = 'webhook';

    public string $ami_host = '';

    public int $ami_port = 5038;

    public string $ami_username = 'callcenter-ai';

    public string $ami_password = '';

    #[Computed]
    public function isCustomProvider(): bool
    {
        if (! $this->voip_provider_id) {
            return false;
        }

        return VoipProvider::query()
            ->whereKey($this->voip_provider_id)
            ->value('code') === VoipProviderCode::Custom->value;
    }

    #[Computed]
    public function amiIngestionAvailable(): bool
    {
        return (bool) config('voip.ami_enabled', false);
    }

    public function updatedVoipProviderId(): void
    {
        $provider = VoipProvider::query()->find($this->voip_provider_id);
        $defaultUrl = $provider?->config['default_api_url'] ?? null;

        if (is_string($defaultUrl) && $defaultUrl !== '' && blank($this->api_url)) {
            $this->api_url = $defaultUrl;
        }
    }

    /** @return array<string, mixed> */
    protected function buildPayload(array $data): array
    {
        $ingestionMode = ($data['ingestion_mode'] ?? 'webhook') === 'ami' && $this->amiIngestionAvailable
            ? 'ami'
            : 'webhook';

        $settings = [
            'timeout' => $data['timeout'],
            'webhook_field_mapping' => $this->decodeJsonObject($data['webhook_field_mapping_json']),
            'extension_mapping' => $this->decodeJsonObject($data['extension_mapping_json']),
            'recording_settings' => $this->decodeJsonObject($data['recording_settings_json']),
            'extra' => array_filter([
                'context' => trim($data['simotel_context'] ?? '') ?: null,
                'ami' => $this->isCustomProvider && $ingestionMode === 'ami' ? array_filter([
                    'host' => trim($data['ami_host'] ?? '') ?: null,
                    'port' => (int) ($data['ami_port'] ?? 5038),
                ]) : null,
            ]),
        ];

        $credentials = $this->isCustomProvider ? array_filter([
            'ami_username' => $ingestionMode === 'ami' ? trim($data['ami_username'] ?? '') ?: null : null,
            'ami_password' => $ingestionMode === 'ami' ? ($data['ami_password'] ?: null) : null,
        ]) : [
            'api_url' => $data['api_url'] ?: null,
            'api_key' => $data['api_key'] ?: null,
            'api_token' => $data['api_token'] ?: null,
            'username' => $data['username'] ?: null,
            'password' => $data['password'] ?: null,
        ];

        return [
            'voip_provider_id' => $data['voip_provider_id'],
            'name' => $data['name'],
            'is_default' => $data['is_default'],
            'is_active' => $data['is_active'],
            'webhook_token' => $data['webhook_token'] ?: null,
            'ingestion_mode' => $ingestionMode,
            'credentials' => $credentials,
            'settings' => $settings,
        ];
    }

    /** @return array<string, string> */
    protected function validationRules(bool $creating): array
    {
        $amiRequired = $this->amiIngestionAvailable && $this->isCustomProvider && $this->ingestion_mode === 'ami';

        return [
            'voip_provider_id' => ['required', 'exists:voip_providers,id'],
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'webhook_token' => ['nullable', 'regex:/^[A-Za-z0-9]{48}$/'],
            'api_url' => [$this->isCustomProvider ? 'nullable' : 'required', 'nullable', 'url'],
            'api_key' => ['nullable', 'string'],
            'api_token' => ['nullable', 'string'],
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'timeout' => ['integer', 'min:5', 'max:120'],
            'simotel_context' => ['nullable', 'string', 'max:255'],
            'webhook_field_mapping_json' => ['nullable', 'string'],
            'extension_mapping_json' => ['nullable', 'string'],
            'recording_settings_json' => ['nullable', 'string'],
            'ingestion_mode' => ['nullable', 'in:webhook,ami'],
            'ami_host' => [$amiRequired ? 'required' : 'nullable', 'string', 'max:255'],
            'ami_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ami_username' => [$amiRequired ? 'required' : 'nullable', 'string', 'max:255'],
            'ami_password' => [$amiRequired && $creating ? 'required' : 'nullable', 'string'],
        ];
    }

    /** @return array<string, mixed> */
    protected function decodeJsonObject(string $json): array
    {
        if (trim($json) === '' || trim($json) === '{}') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function encodeJsonObject(?array $value): string
    {
        return json_encode($value ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    protected function ensureAuthorized(): void
    {
        EmployerIntegrationGate::authorizeFullManagement();
    }

    protected function providers()
    {
        return VoipProvider::query()->where('is_active', true)->orderBy('name')->get();
    }

    protected function organizationId(): int
    {
        return EmployerContext::organizationId();
    }
}
