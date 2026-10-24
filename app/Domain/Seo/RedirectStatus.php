<?php

namespace App\Domain\Seo;

enum RedirectStatus: int
{
    case MovedPermanently = 301;
    case Found = 302;
    case TemporaryRedirect = 307;
    case PermanentRedirect = 308;
    case Gone = 410;

    public function label(): string
    {
        return __('seo.redirects.statuses.'.$this->value);
    }

    public function needsTarget(): bool
    {
        return $this !== self::Gone;
    }

    public function isPermanent(): bool
    {
        return in_array($this, [self::MovedPermanently, self::PermanentRedirect], true);
    }

    /** @return array<int, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
