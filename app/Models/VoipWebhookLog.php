<?php

namespace App\Models;

use App\Domain\Voip\Enums\VoipLogStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_voip_connection_id',
    'event_type',
    'resolved_extension',
    'organization_user_id',
    'status',
    'payload',
    'message',
])]
class VoipWebhookLog extends Model
{
    protected function casts(): array
    {
        return [
            'status' => VoipLogStatus::class,
            'payload' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(OrganizationVoipConnection::class, 'organization_voip_connection_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(OrganizationUser::class, 'organization_user_id');
    }
}
