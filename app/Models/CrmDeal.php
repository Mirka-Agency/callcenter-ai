<?php

namespace App\Models;

use App\Domain\Crm\Deals\DealHealthLevel;
use App\Domain\Crm\Deals\DealSource;
use App\Domain\Crm\Deals\DealStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id',
    'crm_pipeline_id',
    'crm_pipeline_stage_id',
    'customer_company_id',
    'owner_organization_user_id',
    'title',
    'description',
    'amount_rials',
    'currency',
    'status',
    'source',
    'expected_close_on',
    'last_activity_at',
    'closed_at',
    'lost_reason',
    'version',
    'stage_entered_at',
    'won_reason',
    'lost_reason_code',
    'health_score',
    'health_level',
    'health_factors_json',
    'health_calculated_at',
    'next_action_text',
    'next_action_due_on',
])]
class CrmDeal extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (CrmDeal $deal): void {
            if (! $deal->organization_id || ! $deal->crm_pipeline_id || ! $deal->crm_pipeline_stage_id) {
                throw new \RuntimeException('Deals must belong to an organization, pipeline, and stage.');
            }

            if (is_string($deal->lost_reason) && trim($deal->lost_reason) === '') {
                $deal->lost_reason = null;
            }

            $status = $deal->status instanceof DealStatus ? $deal->status : DealStatus::from((string) $deal->status);

            if ($status === DealStatus::Open && ($deal->closed_at !== null || filled($deal->lost_reason))) {
                throw new \RuntimeException('An open deal cannot keep a close date or loss reason.');
            }

            if ($status === DealStatus::Won && (filled($deal->lost_reason) || filled($deal->lost_reason_code))) {
                throw new \RuntimeException('A won deal cannot keep a loss reason.');
            }

            if ($status === DealStatus::Lost && blank($deal->lost_reason) && blank($deal->lost_reason_code)) {
                throw new \RuntimeException('A lost deal requires a reason.');
            }

            if ($status === DealStatus::Open && filled($deal->won_reason)) {
                throw new \RuntimeException('An open deal cannot keep a win reason.');
            }

            if ($status->isClosed() && $deal->closed_at === null) {
                throw new \RuntimeException('A closed deal requires a close date.');
            }

            if ($deal->currency !== 'IRR') {
                throw new \RuntimeException('Deal amounts are stored in rials.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount_rials' => 'integer',
            'status' => DealStatus::class,
            'source' => DealSource::class,
            'expected_close_on' => 'date',
            'last_activity_at' => 'datetime',
            'closed_at' => 'datetime',
            'version' => 'integer',
            'stage_entered_at' => 'datetime',
            'health_score' => 'integer',
            'health_level' => DealHealthLevel::class,
            'health_factors_json' => 'array',
            'health_calculated_at' => 'datetime',
            'next_action_due_on' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'crm_pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmPipelineStage::class, 'crm_pipeline_stage_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CustomerCompany::class, 'customer_company_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(OrganizationUser::class, 'owner_organization_user_id');
    }

    /** @return HasMany<CrmDealContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(CrmDealContact::class);
    }

    /** @return HasOne<CrmDealContact, $this> */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(CrmDealContact::class)->where('is_primary', true);
    }

    /** @return HasMany<CrmDealCall, $this> */
    public function dealCalls(): HasMany
    {
        return $this->hasMany(CrmDealCall::class);
    }

    /** @return HasMany<CrmDealStageHistory, $this> */
    public function stageHistory(): HasMany
    {
        return $this->hasMany(CrmDealStageHistory::class);
    }

    /** @return HasMany<CrmDealActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(CrmDealActivity::class);
    }

    /** @return HasMany<CrmDealAiSuggestion, $this> */
    public function suggestions(): HasMany
    {
        return $this->hasMany(CrmDealAiSuggestion::class, 'applied_crm_deal_id');
    }

    /** @return HasMany<CrmDealExternalLink, $this> */
    public function externalLinks(): HasMany
    {
        return $this->hasMany(CrmDealExternalLink::class);
    }

    /** @return array<string, mixed> */
    public function auditSnapshot(): array
    {
        return [
            'title' => $this->title,
            'status' => $this->status instanceof DealStatus ? $this->status->value : (string) $this->status,
            'stage_id' => $this->crm_pipeline_stage_id ? (int) $this->crm_pipeline_stage_id : null,
            'owner_organization_user_id' => $this->owner_organization_user_id ? (int) $this->owner_organization_user_id : null,
            'amount_rials' => $this->amount_rials,
            'currency' => $this->currency,
            'customer_company_id' => $this->customer_company_id ? (int) $this->customer_company_id : null,
            'expected_close_on' => $this->expected_close_on?->toDateString(),
            'lost_reason' => $this->lost_reason,
            'lost_reason_code' => $this->lost_reason_code,
            'won_reason' => $this->won_reason,
            'description' => $this->description,
            'next_action_text' => $this->next_action_text,
            'next_action_due_on' => $this->next_action_due_on?->toDateString(),
            'version' => (int) $this->version,
        ];
    }

    /** @return HasMany<CrmDealBarrier, $this> */
    public function barriers(): HasMany
    {
        return $this->hasMany(CrmDealBarrier::class);
    }

    /** @return HasMany<CrmTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'crm_deal_id');
    }
}
