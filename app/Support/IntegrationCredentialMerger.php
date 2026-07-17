<?php

namespace App\Support;

class IntegrationCredentialMerger
{
    /** @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    public static function merge(array $incoming, ?array $existing): array
    {
        if ($existing === null) {
            return $incoming;
        }

        foreach ($incoming as $key => $value) {
            if (blank($value) && array_key_exists($key, $existing)) {
                $incoming[$key] = $existing[$key];
            }
        }

        return $incoming;
    }

    /** @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    public static function mergeSettings(array $incoming, ?array $existing): array
    {
        if ($existing === null) {
            return $incoming;
        }

        return array_replace_recursive($existing, array_filter(
            $incoming,
            fn (mixed $value): bool => ! blank($value) || is_bool($value) || is_array($value),
        ));
    }
}
