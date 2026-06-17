<?php

namespace App\Models;

use App\Services\WalletService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'disabled', 'user_id', 'is_demo'])]
class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
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
            'is_demo' => 'boolean',
        ];
    }

    public function isDemo(): bool
    {
        return (bool) $this->is_demo;
    }

    /** @param \Illuminate\Database\Eloquent\Builder<static> $query */
    public function scopeDemo(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
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

    public function wallet(): \Illuminate\Database\Eloquent\Relations\HasOne
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
