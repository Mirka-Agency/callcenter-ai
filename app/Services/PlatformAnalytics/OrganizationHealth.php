<?php

namespace App\Services\PlatformAnalytics;

use App\Support\JalaliDate;
use Carbon\Carbon;

final readonly class OrganizationHealth
{
    public const HEALTHY = 'healthy';

    public const AT_RISK = 'at_risk';

    public const CRITICAL = 'critical';

    /**
     * @param  list<array{key: string, params: array<string, int|string>}>  $reasons
     */
    public function __construct(
        public int $organizationId,
        public int $score,
        public int $analysesLast30Days,
        public int $analysesPrevious30Days,
        public ?Carbon $lastAnalysisAt,
        public array $reasons,
    ) {}

    public function band(): string
    {
        return match (true) {
            $this->score >= 70 => self::HEALTHY,
            $this->score >= 40 => self::AT_RISK,
            default => self::CRITICAL,
        };
    }

    public function color(): string
    {
        return match ($this->band()) {
            self::HEALTHY => 'success',
            self::AT_RISK => 'warning',
            default => 'danger',
        };
    }

    public function label(): string
    {
        return __('filament.platform_analytics.health_bands.'.$this->band());
    }

    /** @return list<string> */
    public function reasonLabels(): array
    {
        return array_map(
            fn (array $reason): string => __(
                'filament.platform_analytics.health_reasons.'.$reason['key'],
                array_map(fn (int|string $value): string => JalaliDate::persianDigits($value), $reason['params']),
            ),
            $this->reasons,
        );
    }
}
