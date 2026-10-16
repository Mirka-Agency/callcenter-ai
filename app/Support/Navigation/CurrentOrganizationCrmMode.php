<?php

namespace App\Support\Navigation;

use App\Domain\Crm\OrganizationCrmMode;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;

final class CurrentOrganizationCrmMode
{
    public static function organization(): ?Organization
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        return match ($user->role) {
            UserRole::Employee => Organization::query()
                ->whereHas('memberships', fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->where('is_active', true))
                ->first(),
            UserRole::Employer => $user->primaryOrganization(),
            default => null,
        };
    }

    public static function isConfigured(): bool
    {
        $organization = self::organization();

        // No tenant in context (e.g. structural nav checks): show default internal CRM IA.
        if ($organization === null) {
            return true;
        }

        return $organization->hasConfiguredCrmMode();
    }

    public static function resolve(): OrganizationCrmMode
    {
        $organization = self::organization();

        if ($organization === null) {
            return OrganizationCrmMode::Internal;
        }

        if (! $organization->hasConfiguredCrmMode()) {
            // Fail closed: do not activate internal CRM operations in the menu.
            return OrganizationCrmMode::External;
        }

        return $organization->crmMode();
    }
}
