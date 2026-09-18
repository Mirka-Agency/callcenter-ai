<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'name',
    'normalized_name',
    'industry',
    'website',
    'phone',
    'email',
    'address',
    'notes',
    'contacts_count',
    'total_calls',
    'latest_lead_score',
    'latest_lead_level',
    'conversation_trend',
    'recommended_next_action',
    'first_contact_at',
    'last_contact_at',
])]
class CustomerCompany extends Model
{
    protected static function booted(): void
    {
        static::saving(function (CustomerCompany $company): void {
            if (! $company->organization_id) {
                throw new \RuntimeException('Customer company records must belong to an organization.');
            }

            if ($company->name) {
                $company->name = trim($company->name);
                $company->normalized_name = self::normalizeName($company->name);
            }

            if (! $company->normalized_name) {
                throw new \RuntimeException('Customer company records must have a name.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'first_contact_at' => 'datetime',
            'last_contact_at' => 'datetime',
        ];
    }

    public static function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return mb_strtolower($name, 'UTF-8');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<Customer, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(Customer::class)->orderByDesc('last_contact_at');
    }

    /** @param  Builder<CustomerCompany>  $query */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function displayName(): string
    {
        return $this->name ?: 'شرکت';
    }
}
