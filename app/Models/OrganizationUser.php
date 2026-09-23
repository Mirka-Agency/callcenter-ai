<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Concerns\HasAvatar;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'organization_id',
    'user_id',
    'first_name',
    'last_name',
    'gender',
    'mobile',
    'position',
    'department',
    'is_active',
])]
class OrganizationUser extends Pivot
{
    use HasAvatar;

    public $incrementing = true;

    protected $table = 'organization_user';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'gender' => Gender::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function integrationMeta(): HasMany
    {
        return $this->hasMany(EmployeeIntegrationMeta::class, 'organization_user_id');
    }

    public function conversationAnalyses(): HasMany
    {
        return $this->hasMany(ConversationAnalysis::class, 'organization_user_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: ($this->user?->name ?? '');
    }

    public function getIntegrationSummaryAttribute(): string
    {
        return $this->integrationMeta()
            ->with('integratable')
            ->get()
            ->groupBy(fn (EmployeeIntegrationMeta $meta) => class_basename($meta->integratable_type))
            ->map(fn ($group, $type) => $type.': '.$group->count())
            ->implode(' · ') ?: '—';
    }
}
