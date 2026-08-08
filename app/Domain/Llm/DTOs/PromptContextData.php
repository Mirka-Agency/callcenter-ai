<?php

namespace App\Domain\Llm\DTOs;

readonly class PromptContextData
{
    public function __construct(
        public ?string $employeeName = null,
        public ?string $department = null,
        public ?string $position = null,
        public ?string $callDirection = null,
        public ?int $callDurationSeconds = null,
        public ?string $customerNumber = null,
        public ?string $title = null,
        public ?string $customerName = null,
        public ?string $category = null,
        public ?string $notes = null,
        public ?string $organizationName = null,
        public ?string $organizationBusinessContext = null,
    ) {}
}
