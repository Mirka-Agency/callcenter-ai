<?php

namespace App\Domain\Llm\Enums;

enum AnalysisSentiment: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';
    case Mixed = 'mixed';

    public static function fromAnalysisValue(mixed $value): self
    {
        $normalized = mb_strtolower(trim((string) $value));

        return match ($normalized) {
            'positive', 'مثبت' => self::Positive,
            'negative', 'منفی' => self::Negative,
            'mixed', 'ترکیبی', 'مختلط', 'مخلوط' => self::Mixed,
            default => self::Neutral,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Positive => 'مثبت',
            self::Neutral => 'خنثی',
            self::Negative => 'منفی',
            self::Mixed => 'ترکیبی',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Positive => 'success',
            self::Neutral => 'gray',
            self::Negative => 'danger',
            self::Mixed => 'warning',
        };
    }
}
