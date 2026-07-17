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
        $settings = [
            'timeout' => $data['timeout'],
            'webhook_field_mapping' => $this->decodeJsonObject($data['webhook_field_mapping_json']),
            'extension_mapping' => $this->decodeJsonObject($data['extension_mapping_json']),
            'recording_settings' => $this->decodeJsonObject($data['recording_settings_json']),
        ];

        return [
            'voip_provider_id' => $data['voip_provider_id'],
            'name' => $data['name'],
            'is_default' => $data['is_default'],
            'is_active' => $data['is_active'],
            'webhook_token' => $data['webhook_token'] ?: null,
            'credentials' => $this->isCustomProvider ? [] : [
                'api_url' => $data['api_url'] ?: null,
                'api_key' => $data['api_key'] ?: null,
                'api_token' => $data['api_token'] ?: null,
                'username' => $data['username'] ?: null,
                'password' => $data['password'] ?: null,
            ],
            'settings' => $settings,
        ];
    }

    /** @return array<string, string> */
    protected function validationRules(bool $creating): array
    {
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
            'webhook_field_mapping_json' => ['nullable', 'string'],
            'extension_mapping_json' => ['nullable', 'string'],
            'recording_settings_json' => ['nullable', 'string'],
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
