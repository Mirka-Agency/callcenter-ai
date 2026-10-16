<?php

namespace App\Application\Customer;

/**
 * Decides whether an extracted value may fill an empty field.
 * A global confidence score never overwrites an existing value.
 */
class FieldEnrichmentPolicy
{
    public const MIN_CONFIDENCE_WITH_EVIDENCE = 0.5;

    public function decision(?string $current, bool $locked, ?string $incoming, ?string $evidence, float $confidence): string
    {
        $incoming = trim((string) $incoming);

        if ($incoming === '') {
            return 'keep';
        }

        if ($locked || filled($current)) {
            return trim((string) $current) === $incoming ? 'keep' : 'suggest';
        }

        if ($confidence < self::MIN_CONFIDENCE_WITH_EVIDENCE || ! $this->attributesToExternalParty($evidence)) {
            return 'suggest';
        }

        return 'fill';
    }

    public function attributesToExternalParty(?string $evidence): bool
    {
        $evidence = trim((string) $evidence);

        if ($evidence === '') {
            return false;
        }

        if (preg_match('/با\s+(آقای|خانم|جناب|مهندس|دکتر)/u', $evidence) === 1) {
            return false;
        }

        return str_contains($evidence, 'مشتری گفت') || str_contains($evidence, 'مشتری:');
    }
}
