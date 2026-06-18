<?php

namespace App\Models;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'call_id', 'source_url', 'storage_disk', 'storage_path', 'mime_type',
    'file_size_bytes', 'duration_seconds', 'channels', 'status', 'error_message', 'downloaded_at',
    'uploaded_at', 'expires_at', 'is_expired', 'expired_at',
])]
class CallRecording extends Model
{
    protected function casts(): array
    {
        return [
            'downloaded_at' => 'datetime',
            'uploaded_at' => 'datetime',
            'expires_at' => 'datetime',
            'expired_at' => 'datetime',
            'is_expired' => 'boolean',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function isExpired(): bool
    {
        return app(\App\Services\RecordingRetentionService::class)->isExpired($this);
    }

    /** @param Builder<CallRecording> $query */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('is_expired', false)
            ->where(function (Builder $inner) {
                $inner->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /** @param Builder<CallRecording> $query */
    public function scopeDueForPurge(Builder $query): Builder
    {
        return $query
            ->where('is_expired', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where(function (Builder $inner) {
                $inner->whereNotNull('storage_path')
                    ->orWhereNotNull('source_url');
            })
            ->whereHas('call', function (Builder $call) {
                $call->where(function (Builder $inner) {
                    $inner->whereNull('processing_status')
                        ->orWhereIn('processing_status', [
                            CallProcessingStatus::Analyzed->value,
                            CallProcessingStatus::Failed->value,
                        ]);
                })->whereDoesntHave('processingJobs', function (Builder $jobs) {
                    $jobs->whereIn('status', [
                        ProcessingJobStatus::Uploading->value,
                        ProcessingJobStatus::Queued->value,
                        ProcessingJobStatus::Processing->value,
                    ]);
                });
            });
    }
}
