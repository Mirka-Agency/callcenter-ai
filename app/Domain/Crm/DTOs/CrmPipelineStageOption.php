<?php

namespace App\Domain\Crm\DTOs;

readonly class CrmPipelineStageOption
{
    public function __construct(
        public string $id,
        public string $title,
        public int $index = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? $data['Id'] ?? ''),
            title: (string) ($data['title'] ?? $data['Title'] ?? $data['name'] ?? $data['Name'] ?? ''),
            index: (int) ($data['index'] ?? $data['Index'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'index' => $this->index,
        ];
    }
}
