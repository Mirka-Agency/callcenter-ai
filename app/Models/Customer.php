<?php

namespace App\Models;

use App\Domain\Customer\CustomerLifecycleStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'customer_company_id',
    'owner_organization_user_id',
    'lifecycle_stage',
    'normalized_phone',
    'phone_number',
    'name',
    'company_name',
    'email',
    'job_title',
    'identity_confidence',
    'identity_field_sources',
    'latest_lead_score',
    'latest_lead_level',
    'common_concerns_json',
    'purchase_intent',
    'conversation_trend',
    'recommended_next_action',
    'first_contact_at',
    'last_contact_at',
    'total_calls',
    'total_answered_calls',
])]
class Customer extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            if (! $customer->organization_id) {
                throw new \RuntimeException('Customer records must belong to an organization.');
            }

            if (! $customer->normalized_phone) {
                throw new \RuntimeException('Customer records must be keyed by organization and phone.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'identity_confidence' => 'float',
            'identity_field_sources' => 'array',
            'lifecycle_stage' => CustomerLifecycleStage::class,
            'common_concerns_json' => 'array',
            'first_contact_at' => 'datetime',
            'last_contact_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CustomerCompany::class, 'customer_company_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(OrganizationUser::class, 'owner_organization_user_id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    /** @return HasMany<CustomerContactMethod, $this> */
    public function contactMethods(): HasMany
    {
        return $this->hasMany(CustomerContactMethod::class);
    }

    /** @return HasMany<CustomerLifecycleEvent, $this> */
    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(CustomerLifecycleEvent::class);
    }

    /** @param  Builder<Customer>  $query */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function displayName(): string
    {
        return $this->name
            ?: $this->company?->name
            ?: $this->company_name
            ?: $this->phone_number
            ?: 'مشتری';
    }

    public function companyLabel(): ?string
    {
        return $this->company?->name ?: $this->company_name;
    }
}
