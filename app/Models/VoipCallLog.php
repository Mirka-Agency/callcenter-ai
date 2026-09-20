<?php

namespace App\Models;

use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Models\Concerns\OccurredBetween;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'organization_voip_connection_id',
    'provider_code',
    'external_call_id',
    'direction',
    'source_number',
    'destination_number',
    'status',
    'started_at',
    'ended_at',
    'duration',
    'recording_url',
    'raw_payload',
])]
class VoipCallLog extends Model
{
    use OccurredBetween;

    protected function casts(): array
    {
        return [
            'direction' => CallDirection::class,
            'status' => CallStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(OrganizationVoipConnection::class, 'organization_voip_connection_id');
    }

    public function analyses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ConversationAnalysis::class, 'voip_call_log_id');
    }
}
