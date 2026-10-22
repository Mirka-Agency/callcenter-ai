<?php

namespace App\Enums;

enum ContactMessageSubject: string
{
    case Product = 'product';
    case Consultation = 'consultation';
    case Support = 'support';
    case Partnership = 'partnership';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'سوال درباره محصول',
            self::Consultation => 'درخواست مشاوره',
            self::Support => 'پشتیبانی',
            self::Partnership => 'همکاری',
            self::Other => 'سایر',
        };
    }

    public static function labelOf(mixed $state): string
    {
        $subject = $state instanceof self
            ? $state
            : self::tryFrom((string) $state);

        return $subject?->label() ?? '—';
    }
}
