<?php

namespace App\Support;

/**
 * Field-level identity provenance. A human save locks the field;
 * later AI suggestions must not replace it. Records without a source
 * keep the previous confidence-based enrichment behavior.
 */
class CustomerFieldProvenance
{
    public const HUMAN = 'human';

    public const AI = 'ai';

    /** @var list<string> */
    public const LOCKABLE = [
        'name',
        'email',
        'job_title',
        'phone_number',
        'company_name',
        'customer_company_id',
    ];

    public static function isHumanLocked(?array $sources, string $field): bool
    {
        $entry = $sources[$field] ?? null;

        if (! is_array($entry)) {
            return false;
        }

        return ($entry['source'] ?? null) === self::HUMAN || ($entry['verified'] ?? false) === true;
    }

    /**
     * Lock only non-empty values the user actually submitted.
     *
     * @param  array<string, mixed>  $sources
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function lockProvided(array $sources, array $values): array
    {
        foreach (self::LOCKABLE as $field) {
            if (! array_key_exists($field, $values) || blank($values[$field])) {
                continue;
            }

            $sources[$field] = self::humanEntry();
        }

        return $sources;
    }

    /**
     * Lock only fields whose value changed. Absent fields stay untouched.
     *
     * @param  array<string, mixed>  $sources
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    public static function lockChanged(array $sources, array $before, array $after): array
    {
        foreach (self::LOCKABLE as $field) {
            if (! array_key_exists($field, $after)) {
                continue;
            }

            $previous = array_key_exists($field, $before) ? $before[$field] : null;

            if (self::sameValue($previous, $after[$field])) {
                continue;
            }

            $sources[$field] = self::humanEntry();
        }

        return $sources;
    }

    public static function companyIsHumanLocked(?array $sources): bool
    {
        return self::isHumanLocked($sources, 'company_name')
            || self::isHumanLocked($sources, 'customer_company_id');
    }

    public static function hasHumanLock(?array $sources): bool
    {
        if ($sources === null) {
            return false;
        }

        foreach (self::LOCKABLE as $field) {
            if (self::isHumanLocked($sources, $field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $sources
     * @return array<string, mixed>
     */
    public static function lockManual(array $sources): array
    {
        $stamp = now()->toIso8601String();

        foreach (self::LOCKABLE as $field) {
            $sources[$field] = [
                'source' => self::HUMAN,
                'updated_at' => $stamp,
            ];
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $sources
     * @return array<string, mixed>
     */
    public static function markAi(array $sources, string $field, float $confidence): array
    {
        if (self::isHumanLocked($sources, $field)) {
            return $sources;
        }

        $sources[$field] = [
            'source' => self::AI,
            'confidence' => $confidence,
            'updated_at' => now()->toIso8601String(),
        ];

        return $sources;
    }

    /** @return array{source: string, verified: bool, updated_at: string} */
    private static function humanEntry(): array
    {
        return [
            'source' => self::HUMAN,
            'verified' => true,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    private static function sameValue(mixed $left, mixed $right): bool
    {
        $left = blank($left) ? null : (is_string($left) ? trim($left) : $left);
        $right = blank($right) ? null : (is_string($right) ? trim($right) : $right);

        return $left == $right;
    }
}
