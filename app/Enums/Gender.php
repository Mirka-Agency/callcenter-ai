<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'مرد',
            self::Female => 'زن',
        };
    }

    public function agentIcon(): string
    {
        return match ($this) {
            self::Male => 'agent-male',
            self::Female => 'agent-female',
        };
    }

    public static function tryFromMixed(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $gender) => [$gender->value => $gender->label()])
            ->all();
    }
}
