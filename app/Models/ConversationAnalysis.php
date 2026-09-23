<?php

namespace App\Models;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'organization_user_id',
    'voip_call_log_id',
    'llm_provider',
    'model_name',
    'prompt_version',
    'score',
    'is_evaluable',
    'needs_attention',
    'attention_json',
    'summary',
    'transcript',
    'sentiment',
    'overall_evaluation',
    'strengths_json',
    'weaknesses_json',
    'next_actions_json',
    'call_id',
    'source',
    'performance_dimensions_json',
    'customer_insights_json',
    'operational_insights_json',
    'lead_quality_json',
    'concerns_json',
    'customer_identity_json',
    'input_tokens',
    'output_tokens',
    'total_tokens',
    'cost',
    'llm_model_id',
    'input_price_snapshot',
    'output_price_snapshot',
    'cached_input_price_snapshot',
    'reasoning_price_snapshot',
    'processing_duration_ms',
    'analyzed_at',
])]
class ConversationAnalysis extends Model
{
    protected function casts(): array
    {
        return [
            'source' => ConversationSource::class,
            'sentiment' => AnalysisSentiment::class,
            'strengths_json' => 'array',
            'weaknesses_json' => 'array',
            'next_actions_json' => 'array',
            'performance_dimensions_json' => 'array',
            'customer_insights_json' => 'array',
            'operational_insights_json' => 'array',
            'lead_quality_json' => 'array',
            'concerns_json' => 'array',
            'customer_identity_json' => 'array',
            'is_evaluable' => 'boolean',
            'needs_attention' => 'boolean',
            'attention_json' => 'array',
            'cost' => 'decimal:6',
            'input_price_snapshot' => 'decimal:6',
            'output_price_snapshot' => 'decimal:6',
            'cached_input_price_snapshot' => 'decimal:6',
            'reasoning_price_snapshot' => 'decimal:6',
            'analyzed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(OrganizationUser::class, 'organization_user_id');
    }

    public function callLog(): BelongsTo
    {
        return $this->belongsTo(VoipCallLog::class, 'voip_call_log_id');
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    /**
     * Day the conversation happened — prefer call time over analysis completion.
     */
    public function occurredAt(): ?CarbonInterface
    {
        return $this->call?->occurredAt() ?? $this->analyzed_at;
    }

    public function llmModel(): BelongsTo
    {
        return $this->belongsTo(LlmModel::class, 'llm_model_id');
    }

    public function crmSyncs(): HasMany
    {
        return $this->hasMany(CrmPipelineSync::class, 'conversation_analysis_id');
    }

    public function isEvaluable(): bool
    {
        return ($this->is_evaluable ?? true) && (int) $this->score > 0;
    }

    /** @param Builder<ConversationAnalysis> $query */
    public function scopeEvaluable(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where($table.'.is_evaluable', true)->where($table.'.score', '>', 0);
    }
}
