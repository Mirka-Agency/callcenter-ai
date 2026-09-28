<?php

namespace App\Models;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Call\Enums\UploaderType;
use App\Models\Concerns\OccurredBetween;
use App\Services\CustomerPhoneResolver;
use App\Services\Reports\DefinedExtensionCallConstraint;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'organization_id', 'organization_user_id', 'organization_voip_connection_id',
    'organization_crm_connection_id', 'voip_call_log_id', 'provider_code', 'external_call_id',
    'source', 'uploader_id', 'uploader_type',
    'direction', 'caller_number', 'receiver_number', 'status',
    'processing_status', 'processing_error',
    'started_at', 'ended_at',
    'duration_seconds', 'metadata', 'title', 'customer_name', 'customer_phone', 'customer_id',
    'notes', 'category', 'tags', 'conversation_date',
    'counts_for_extension_reports',
])]
class Call extends Model
{
    use OccurredBetween;

    protected function casts(): array
    {
        return [
            'source' => ConversationSource::class,
            'uploader_type' => UploaderType::class,
            'processing_status' => CallProcessingStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'conversation_date' => 'datetime',
            'metadata' => 'array',
            'tags' => 'array',
            'counts_for_extension_reports' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Call $call): void {
            if (! $call->customer_id) {
                return;
            }

            $customer = $call->relationLoaded('customer')
                ? $call->customer
                : Customer::query()->find($call->customer_id);

            if ($customer && $customer->organization_id !== $call->organization_id) {
                throw new \RuntimeException('Cannot link a call to a customer outside its organization.');
            }
        });

        static::saving(function (Call $call): void {
            $call->counts_for_extension_reports = app(DefinedExtensionCallConstraint::class)
                ->countsForReports($call);
        });

        static::saving(function (Call $call): void {
            $resolver = app(CustomerPhoneResolver::class);
            $call->normalized_caller_number = $resolver->normalize($call->caller_number);
            $call->normalized_receiver_number = $resolver->normalize($call->receiver_number);
            $call->normalized_customer_phone = $resolver->normalize($call->customer_phone);
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(OrganizationUser::class, 'organization_user_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function voipCallLog(): BelongsTo
    {
        return $this->belongsTo(VoipCallLog::class);
    }

    public function recording(): HasOne
    {
        return $this->hasOne(CallRecording::class)->latestOfMany();
    }

    public function availableRecording(): HasOne
    {
        return $this->hasOne(CallRecording::class)
            ->available()
            ->latestOfMany();
    }

    /**
     * A call has a recording when the PBX sent a URL or a recording row stores a file or source URL.
     *
     * @param  Builder<Call>  $query
     * @return Builder<Call>
     */
    public function scopeWithRecording(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $eligible) use ($table): void {
            $eligible->whereExists(function ($recordings) use ($table): void {
                $recordings->selectRaw('1')
                    ->from('call_recordings')
                    ->whereColumn('call_recordings.call_id', $table.'.id')
                    ->where(function ($file): void {
                        $file->where(function ($path): void {
                            $path->whereNotNull('call_recordings.storage_path')
                                ->where('call_recordings.storage_path', '!=', '');
                        })->orWhere(function ($url): void {
                            $url->whereNotNull('call_recordings.source_url')
                                ->where('call_recordings.source_url', '!=', '');
                        });
                    });
            })->orWhereExists(function ($logs) use ($table): void {
                $logs->selectRaw('1')
                    ->from('voip_call_logs')
                    ->whereColumn('voip_call_logs.id', $table.'.voip_call_log_id')
                    ->whereNotNull('voip_call_logs.recording_url')
                    ->where('voip_call_logs.recording_url', '!=', '');
            });
        });
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(ConversationAnalysis::class);
    }

    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(ConversationAnalysis::class)->latestOfMany('analyzed_at');
    }

    public function processingJob(): HasOne
    {
        return $this->hasOne(CallProcessingJob::class)->latestOfMany();
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(CallProcessingJob::class);
    }

    public function isManualUpload(): bool
    {
        return $this->source === ConversationSource::ManualUpload;
    }

    public function displayTitle(): string
    {
        return $this->title
            ?? $this->customer_name
            ?? 'Upload #'.$this->id;
    }

    /**
     * When the conversation actually happened (manual upload date, VoIP start, or create time).
     */
    public function occurredAt(): ?CarbonInterface
    {
        return $this->conversation_date ?? $this->started_at ?? $this->created_at;
    }

    /**
     * Prefer conversation_date (manual uploads), then started_at, then created_at —
     * same order as occurredAt().
     *
     * @param  Builder<Call>  $query
     * @return Builder<Call>
     */
    public function scopeOccurredBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->whereRaw(
            'COALESCE(conversation_date, started_at, created_at) BETWEEN ? AND ?',
            [$from->toDateTimeString(), $to->toDateTimeString()],
        );
    }

    /** @param Builder<Call> $query */
    public function scopeWithPlayableOrAnalyzedAudio(Builder $query): Builder
    {
        return $query->where(function (Builder $inner) {
            $inner->whereHas('latestAnalysis')
                ->orWhereHas('recording', fn (Builder $recording) => $recording->available())
                ->orWhereDoesntHave('recording');
        });
    }
}
