<?php

namespace App\Models\Concerns;

use App\Domain\Platform\Enums\AdminArea;
use App\Domain\Platform\Enums\LeadStatus;
use App\Models\AdminNote;
use App\Models\Organization;
use App\Models\User;
use App\Services\Admin\AdminAlerts;
use App\Services\Sales\LeadPipelineMetrics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait IsLead
{
    public const SLA_HOURS = 24;

    public static function bootIsLead(): void
    {
        static::created(function (Model $lead): void {
            $resource = collect(LeadPipelineMetrics::types())->firstWhere('model', $lead::class)['resource'] ?? null;

            rescue(fn () => app(AdminAlerts::class)->send(
                AdminArea::Sales,
                __('admin.alerts.new_lead', ['name' => $lead->leadName()]),
                $lead->phone ?? $lead->email ?? null,
                $resource ? $resource::getUrl('view', ['record' => $lead], panel: 'admin') : null,
            ), report: true);
        });
    }

    public function initializeIsLead(): void
    {
        $this->mergeFillable([
            'lead_status', 'assigned_to_user_id', 'follow_up_at', 'first_contacted_at', 'source',
            'utm_source', 'utm_medium', 'utm_campaign', 'converted_organization_id', 'lost_reason',
        ]);

        $this->mergeCasts([
            'lead_status' => LeadStatus::class,
            'follow_up_at' => 'datetime',
            'first_contacted_at' => 'datetime',
        ]);

        $this->attributes['lead_status'] ??= LeadStatus::New->value;
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function convertedOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'converted_organization_id');
    }

    public function adminNotes(): MorphMany
    {
        return $this->morphMany(AdminNote::class, 'notable')->latest();
    }

    public function scopeOpenLeads(Builder $query): Builder
    {
        return $query->whereNotIn($query->qualifyColumn('lead_status'), [LeadStatus::Converted->value, LeadStatus::Lost->value]);
    }

    /** New leads nobody has contacted within the SLA window. */
    public function scopeBreachingSla(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('lead_status'), LeadStatus::New->value)
            ->whereNull($query->qualifyColumn('first_contacted_at'))
            ->whereNull($query->qualifyColumn('archived_at'))
            ->where($query->qualifyColumn('created_at'), '<', now()->subHours(self::SLA_HOURS));
    }

    public function scopeFollowUpDue(Builder $query): Builder
    {
        return $query
            ->openLeads()
            ->whereNotNull($query->qualifyColumn('follow_up_at'))
            ->where($query->qualifyColumn('follow_up_at'), '<=', now());
    }

    public function moveTo(LeadStatus $status): void
    {
        $this->lead_status = $status;

        if ($status !== LeadStatus::New && $this->first_contacted_at === null) {
            $this->first_contacted_at = now();
        }

        $this->save();
    }

    public function leadName(): string
    {
        return (string) ($this->company_name ?? $this->full_name);
    }
}
