<?php

namespace App\Support;

final class MetricTone
{
    /**
     * Same bands as lead quality: 70+ good, 40–69 medium, below 40 poor.
     */
    public static function fromScore(mixed $score): ?string
    {
        if (! is_numeric($score) || (float) $score <= 0) {
            return null;
        }

        $score = (float) $score;

        return match (true) {
            $score >= 70 => 'good',
            $score >= 40 => 'medium',
            default => 'bad',
        };
    }
}
