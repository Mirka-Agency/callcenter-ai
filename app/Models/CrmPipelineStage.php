<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'crm_pipeline_id',
    'name',
    'color',
    'position',
    'is_active',
    'stale_after_days',
])]
class CrmPipelineStage extends Model
{
    protected static function booted(): void
    {
        static::saving(function (CrmPipelineStage $stage): void {
            if (! $stage->organization_id || ! $stage->crm_pipeline_id) {
                throw new \RuntimeException('Pipeline stages must belong to an organization and pipeline.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
            'stale_after_days' => 'integer',
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

    /** @return HasMany<CrmDeal, $this> */
    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'crm_pipeline_stage_id');
    }
}
