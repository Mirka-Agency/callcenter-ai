<?php

namespace App\Models;

use App\Services\WalletService;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['title', 'disabled', 'employer_can_manage_integrations', 'user_id', 'is_demo'])]
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
            'is_demo' => 'boolean',
        ];
    }

    public function isDemo(): bool
    {
        return (bool) $this->is_demo;
    }

    public function employerCanManageIntegrations(): bool
    {
        return (bool) $this->employer_can_manage_integrations;
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
    }
}
