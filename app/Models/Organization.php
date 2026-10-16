<?php

namespace App\Models;

use App\Domain\Crm\OrganizationCrmMode;
use App\Services\CallIntake\CallIntakeSettings;
use App\Services\WalletService;
use App\Support\CompanyWorkCalendar;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['title', 'business_context', 'holiday_weekdays', 'call_intake_filters', 'crm_settings', 'disabled', 'employer_can_manage_integrations', 'crm_mode', 'user_id', 'is_demo', 'assign_customer_owner_from_call'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'disabled' => 'boolean',
            'employer_can_manage_integrations' => 'boolean',
            'crm_mode' => OrganizationCrmMode::class,
            'is_demo' => 'boolean',
            'assign_customer_owner_from_call' => 'boolean',
            'holiday_weekdays' => 'array',
            'call_intake_filters' => 'array',
            'crm_settings' => 'array',
        ];
    }

    /**
     * Weekdays this company closes. Null in the database means Thursday and Friday.
     *
     * @return list<int>
     */
    public function holidayWeekdays(): array
    {
        return CompanyWorkCalendar::normalizeWeekdays($this->holiday_weekdays);
    }

    public function isDemo(): bool
    {
        return (bool) $this->is_demo;
    }

    public function employerCanManageIntegrations(): bool
    {
        return (bool) $this->employer_can_manage_integrations;
    }

    public function hasConfiguredCrmMode(): bool
    {
        return $this->crm_mode instanceof OrganizationCrmMode;
    }

    /**
     * Display helper. Operational gates must use usesInternalCrm/usesExternalCrm
     * which fail closed when mode is missing.
     */
    public function crmMode(): OrganizationCrmMode
    {
        return $this->crm_mode instanceof OrganizationCrmMode
            ? $this->crm_mode
            : OrganizationCrmMode::Internal;
    }

    public function usesInternalCrm(): bool
    {
        return $this->hasConfiguredCrmMode() && $this->crm_mode === OrganizationCrmMode::Internal;
    }

    public function usesExternalCrm(): bool
    {
        return $this->hasConfiguredCrmMode() && $this->crm_mode === OrganizationCrmMode::External;
    }

    /** @param Builder<static> $query */
    public function scopeDemo(Builder $query): Builder
    {
        return $query->where('is_demo', true);
    }

    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->withPivot([
                'first_name',
                'last_name',
                'mobile',
                'position',
                'department',
                'is_active',
            ])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationUser::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OrganizationActivity::class)->latest();
    }

    public function crmConnections(): HasMany
    {
        return $this->hasMany(OrganizationCrmConnection::class);
    }

    public function voipConnections(): HasMany
    {
        return $this->hasMany(OrganizationVoipConnection::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function customerCompanies(): HasMany
    {
        return $this->hasMany(CustomerCompany::class);
    }

    public function conversationAnalyses(): HasMany
    {
        return $this->hasMany(ConversationAnalysis::class);
    }

    public function aiUsageSnapshots(): HasMany
    {
        return $this->hasMany(AiUsageDailySnapshot::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(OrganizationWallet::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest('created_at');
    }

    protected static function booted(): void
    {
        static::created(function (Organization $organization): void {
            app(WalletService::class)->forOrganization($organization->id);
        });

        static::saved(function (Organization $organization): void {
            if ($organization->wasChanged('holiday_weekdays')) {
                app()->forgetInstance('organization.holiday_weekdays.'.$organization->id);
            }

            if ($organization->wasChanged('call_intake_filters')) {
                app(CallIntakeSettings::class)->saved(
                    $organization,
                    $organization->getOriginal('call_intake_filters'),
                );
            }
        });
    }
}
