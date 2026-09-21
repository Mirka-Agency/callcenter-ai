<?php

namespace App\Services\QueueMonitoring;

class QueueJobInspection
{
    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $chainedJobs
     */
    public function __construct(
        public ?string $displayName = null,
        public ?string $jobClass = null,
        public ?string $jobUuid = null,
        public ?int $maxTries = null,
        public ?int $timeout = null,
        public array $properties = [],
        public array $chainedJobs = [],
        public ?string $exceptionMessage = null,
        public ?string $exceptionFull = null,
        public ?string $failureReason = null,
    ) {}

    public function shortLabel(): string
    {
        return $this->jobClass ?? class_basename($this->displayName ?? '') ?: __('filament.misc.unknown_job');
    }

    public function callId(): ?int
    {
        $callId = $this->properties['callId'] ?? $this->properties['call_id'] ?? null;

        return is_int($callId) ? $callId : (is_numeric($callId) ? (int) $callId : null);
    }

    /** @return array<string, mixed> */
    public function propertiesForDisplay(): array
    {
        return collect($this->properties)
            ->map(fn (mixed $value) => match (true) {
                is_scalar($value) || $value === null => $value,
                is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                default => (string) $value,
            })
            ->all();
    }
}
