<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\CustomerCompany;

class CustomerCompanyPresenter
{
    public static function subtitle(CustomerCompany $company): string
    {
        return collect([
            $company->industry,
            $company->phone,
            $company->email,
        ])->filter()->implode(' · ') ?: '—';
    }

    public static function metaLine(CustomerCompany $company): string
    {
        return collect([
            $company->contacts_count.' شخص',
            $company->total_calls.' تماس',
            $company->last_contact_at ? JalaliDate::ago($company->last_contact_at) : null,
        ])->filter()->implode(' · ');
    }

    public static function contactPreview(CustomerCompany $company, int $limit = 3): string
    {
        return $company->contacts
            ->take($limit)
            ->map(fn (Customer $contact) => $contact->displayName())
            ->filter()
            ->implode('، ');
    }

    public static function trendBadgeClass(?string $trend): string
    {
        return CustomerPresenter::trendBadgeClass($trend);
    }

    public static function trendLabel(?string $trend): string
    {
        return CustomerPresenter::trendLabel($trend);
    }

    public static function leadBadgeClass(?string $level): string
    {
        return CustomerPresenter::leadBadgeClass($level);
    }
}
