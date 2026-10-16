<?php

namespace App\Support;

/**
 * Valid format is not proof of who owns the number.
 */
class PhoneInspection
{
    public function __construct(
        public ?string $raw,
        public ?string $normalized,
        public ?string $country,
        public bool $isValid,
        public string $reason,
    ) {}
}
