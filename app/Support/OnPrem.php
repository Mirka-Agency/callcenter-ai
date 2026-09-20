<?php

namespace App\Support;

class OnPrem
{
    public static function enabled(): bool
    {
        return (bool) config('onprem.enabled');
    }

    public static function billingHidden(): bool
    {
        return self::enabled();
    }

    public static function llmNavigationGroup(): string
    {
        return self::billingHidden()
            ? __('filament.navigation.groups.ai_management')
            : __('filament.navigation.groups.ai_billing');
    }
}
