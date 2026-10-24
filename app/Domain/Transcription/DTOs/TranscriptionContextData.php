<?php

namespace App\Domain\Transcription\DTOs;

readonly class TranscriptionContextData
{
    public function __construct(
        public ?string $organizationName = null,
        public ?string $organizationBusinessContext = null,
    ) {}
}
