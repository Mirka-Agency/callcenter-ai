<?php

namespace App\Domain\Crm\Deals;

/**
 * Automatic deal creation stays off until an explicit commercial policy exists.
 * Analysis may only create a suggestion that a person accepts.
 */
final class DealAutomationPolicy
{
    public static function autoCreateFromAnalysis(): bool
    {
        return false;
    }
}
