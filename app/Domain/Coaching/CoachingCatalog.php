<?php

namespace App\Domain\Coaching;

/**
 * Single reader for config/coaching.php.
 * Prompt building can run before the HTTP kernel boots, so a failed config()
 * call falls back to requiring the same file.
 */
final class CoachingCatalog
{
    public const STATUS_CRITICAL = 'critical';

    public const STATUS_NEEDS_IMPROVEMENT = 'needs_improvement';

    public const STATUS_GOOD = 'good';

    public const STATUS_STRENGTH = 'strength';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_CRITICAL,
        self::STATUS_NEEDS_IMPROVEMENT,
        self::STATUS_GOOD,
        self::STATUS_STRENGTH,
    ];

    /** @var list<string> */
    public const SEVERITIES = ['low', 'medium', 'high'];

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function skills(): array
    {
        $skills = self::value('skills');
        if (! is_array($skills)) {
            return [];
        }

        $normalized = [];

        foreach ($skills as $skill) {
            if (! is_array($skill)) {
                continue;
            }

            $key = trim((string) ($skill['key'] ?? ''));
            $label = trim((string) ($skill['label'] ?? ''));

            if ($key === '' || $label === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                continue;
            }

            $normalized[] = ['key' => $key, 'label' => $label];
        }

        return $normalized;
    }

    /** @return list<string> */
    public static function skillKeys(): array
    {
        return array_column(self::skills(), 'key');
    }

    public static function knows(string $key): bool
    {
        return in_array($key, self::skillKeys(), true);
    }

    public static function label(string $key): string
    {
        foreach (self::skills() as $skill) {
            if ($skill['key'] === $key) {
                return $skill['label'];
            }
        }

        return $key;
    }

    public static function minimumCalls(): int
    {
        return max(1, (int) self::value('min_analyzed_calls', 5));
    }

    public static function evidenceLimit(): int
    {
        return max(1, (int) self::value('evidence_limit', 8));
    }

    public static function strengthLimit(): int
    {
        return max(1, (int) self::value('strength_limit', 4));
    }

    public static function recommendationLimit(): int
    {
        return max(1, (int) self::value('recommendation_limit', 5));
    }

    public static function trendSkillLimit(): int
    {
        return max(1, (int) self::value('trend_skill_limit', 4));
    }

    public static function minimumTrendPoints(): int
    {
        return max(2, (int) self::value('min_trend_points', 2));
    }

    public static function highSeverityWeakRatio(): float
    {
        $ratio = self::value('high_severity_weak_ratio', 0.4);
        $ratio = is_numeric($ratio) ? (float) $ratio : 0.4;

        return max(0.0, min(1.0, $ratio));
    }

    /**
     * @return list<array{max: float, status: string, label: string}>
     */
    public static function bands(): array
    {
        $bands = self::value('bands');
        if (! is_array($bands)) {
            return self::fallbackBands();
        }

        $normalized = [];

        foreach ($bands as $band) {
            if (! is_array($band) || ! is_numeric($band['max'] ?? null)) {
                continue;
            }

            $status = (string) ($band['status'] ?? '');
            $label = trim((string) ($band['label'] ?? ''));

            if (! in_array($status, self::STATUSES, true) || $label === '') {
                continue;
            }

            $normalized[] = [
                'max' => (float) $band['max'],
                'status' => $status,
                'label' => $label,
            ];
        }

        usort($normalized, fn (array $left, array $right) => $left['max'] <=> $right['max']);

        return $normalized !== [] ? $normalized : self::fallbackBands();
    }

    public static function statusFor(float $score): string
    {
        $score = round(max(0, min(100, $score)), 1);

        foreach (self::bands() as $band) {
            if ($score <= $band['max']) {
                return $band['status'];
            }
        }

        return self::STATUS_STRENGTH;
    }

    public static function statusLabel(string $status): string
    {
        foreach (self::bands() as $band) {
            if ($band['status'] === $status) {
                return $band['label'];
            }
        }

        return $status;
    }

    public static function isWeakStatus(string $status): bool
    {
        return in_array($status, [self::STATUS_CRITICAL, self::STATUS_NEEDS_IMPROVEMENT], true);
    }

    /** @return list<array{max: float, status: string, label: string}> */
    private static function fallbackBands(): array
    {
        return [
            ['max' => 49, 'status' => self::STATUS_CRITICAL, 'label' => 'نیاز جدی به بهبود'],
            ['max' => 69, 'status' => self::STATUS_NEEDS_IMPROVEMENT, 'label' => 'نیاز به بهبود'],
            ['max' => 84, 'status' => self::STATUS_GOOD, 'label' => 'خوب'],
            ['max' => 100, 'status' => self::STATUS_STRENGTH, 'label' => 'قوت'],
        ];
    }

    private static function value(string $key, mixed $default = null): mixed
    {
        try {
            if (function_exists('config')) {
                $value = config('coaching.'.$key);
                if ($value !== null) {
                    return $value;
                }
            }
        } catch (\Throwable) {
            // Prompt unit tests construct services before the application boots.
        }

        $path = dirname(__DIR__, 3).'/config/coaching.php';

        if (! is_file($path)) {
            return $default;
        }

        try {
            $config = require $path;
        } catch (\Throwable) {
            return $default;
        }

        if (! is_array($config) || ! array_key_exists($key, $config)) {
            return $default;
        }

        return $config[$key];
    }
}
