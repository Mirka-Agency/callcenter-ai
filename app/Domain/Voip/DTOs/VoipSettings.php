<?php

namespace App\Domain\Voip\DTOs;

readonly class VoipSettings
{
    public function __construct(
        public ?string $webhookUrl = null,
        public ?string $webhookSecret = null,
        public array $extensionMapping = [],
        public array $recordingSettings = [],
        public array $webhookFieldMapping = [],
        public int $timeout = 30,
        public array $extra = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            webhookUrl: $data['webhook_url'] ?? $data['webhookUrl'] ?? null,
            webhookSecret: $data['webhook_secret'] ?? $data['webhookSecret'] ?? null,
            extensionMapping: $data['extension_mapping'] ?? $data['extensionMapping'] ?? [],
            recordingSettings: $data['recording_settings'] ?? $data['recordingSettings'] ?? [],
            webhookFieldMapping: $data['webhook_field_mapping'] ?? $data['webhookFieldMapping'] ?? [],
            timeout: (int) ($data['timeout'] ?? 30),
            extra: $data['extra'] ?? [],
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'webhook_url' => $this->webhookUrl,
            'webhook_secret' => $this->webhookSecret,
            'extension_mapping' => $this->extensionMapping ?: null,
            'recording_settings' => $this->recordingSettings ?: null,
            'webhook_field_mapping' => $this->webhookFieldMapping ?: null,
            'timeout' => $this->timeout,
            'extra' => $this->extra ?: null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
