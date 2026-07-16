<?php

namespace App\Domain\Crm\DTOs;

readonly class CrmPipelineOption
{
    /**
     * @param  list<CrmPipelineStageOption>  $stages
     */
    public function __construct(
        public string $id,
        public string $title,
        public array $stages = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $stages = [];
        foreach ($data['stages'] ?? $data['Stages'] ?? [] as $stage) {
            if (! is_array($stage)) {
                continue;
            }
            $stages[] = CrmPipelineStageOption::fromArray($stage);
        }

        return new self(
            id: (string) ($data['id'] ?? $data['Id'] ?? ''),
            title: (string) ($data['title'] ?? $data['Title'] ?? $data['name'] ?? $data['Name'] ?? ''),
            stages: $stages,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'stages' => array_map(fn (CrmPipelineStageOption $stage) => $stage->toArray(), $this->stages),
        ];
    }
}
