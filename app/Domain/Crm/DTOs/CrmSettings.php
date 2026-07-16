<?php

namespace App\Domain\Crm\DTOs;

readonly class CrmSettings
{
    public function __construct(
        public ?string $webhookUrl = null,
        public ?string $webhookSecret = null,
        public int $timeout = 30,
        public ?string $pipelineId = null,
        public ?string $pipelineStageId = null,
        public ?string $dealOwnerId = null,
        public array $extra = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            webhookUrl: $data['webhook_url'] ?? $data['webhookUrl'] ?? null,
            webhookSecret: $data['webhook_secret'] ?? $data['webhookSecret'] ?? null,
            timeout: (int) ($data['timeout'] ?? 30),
            pipelineId: self::nullableString($data['pipeline_id'] ?? $data['pipelineId'] ?? null),
            pipelineStageId: self::nullableString($data['pipeline_stage_id'] ?? $data['pipelineStageId'] ?? null),
            dealOwnerId: self::nullableString($data['deal_owner_id'] ?? $data['dealOwnerId'] ?? null),
            extra: $data['extra'] ?? [],
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'webhook_url' => $this->webhookUrl,
            'webhook_secret' => $this->webhookSecret,
            'timeout' => $this->timeout,
            'pipeline_id' => $this->pipelineId,
            'pipeline_stage_id' => $this->pipelineStageId,
            'deal_owner_id' => $this->dealOwnerId,
            'extra' => $this->extra ?: null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function hasDealDefaults(): bool
    {
        return filled($this->pipelineStageId);
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }
}
