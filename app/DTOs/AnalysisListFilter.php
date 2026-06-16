<?php

namespace App\DTOs;

use App\Enums\ReportDatePreset;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

readonly class AnalysisListFilter
{
    /** @param  list<string>  $statuses */
    public function __construct(
        public int $organizationId,
        public ReportDatePreset $preset,
        public Carbon $from,
        public Carbon $to,
        public ?int $employeeId = null,
        public array $statuses = [],
        public ?string $direction = null,
        public ?int $minDurationSeconds = null,
        public ?int $maxDurationSeconds = null,
        public string $search = '',
        public string $sortBy = 'analyzed_at',
        public string $sortDir = 'desc',
    ) {}

    public static function make(
        int $organizationId,
        ReportDatePreset $preset,
        ?Carbon $customFrom = null,
        ?Carbon $customTo = null,
        ?int $employeeId = null,
        array $statuses = [],
        ?string $direction = null,
        ?int $minDurationSeconds = null,
        ?int $maxDurationSeconds = null,
        string $search = '',
        string $sortBy = 'analyzed_at',
        string $sortDir = 'desc',
    ): self {
        [$from, $to] = $preset->resolve($customFrom, $customTo);

        return new self(
            organizationId: $organizationId,
            preset: $preset,
            from: $from,
            to: $to,
            employeeId: $employeeId,
            statuses: array_values(array_filter($statuses)),
            direction: $direction ?: null,
            minDurationSeconds: $minDurationSeconds,
            maxDurationSeconds: $maxDurationSeconds,
            search: trim($search),
            sortBy: $sortBy,
            sortDir: $sortDir === 'asc' ? 'asc' : 'desc',
        );
    }

    public function hasActiveFilters(): bool
    {
        return $this->employeeId !== null
            || $this->hasUserFilters();
    }

    public function hasUserFilters(): bool
    {
        return $this->statuses !== []
            || $this->direction !== null
            || $this->minDurationSeconds !== null
            || $this->maxDurationSeconds !== null
            || $this->search !== ''
            || $this->preset !== ReportDatePreset::Last30;
    }

    /** @param  Builder<\App\Models\ConversationAnalysis>  $query */
    public function apply(Builder $query): Builder
    {
        $query->where('conversation_analyses.organization_id', $this->organizationId)
            ->whereBetween('conversation_analyses.analyzed_at', [$this->from, $this->to]);

        if ($this->employeeId !== null) {
            $query->where('conversation_analyses.organization_user_id', $this->employeeId);
        }

        if ($this->statuses !== []) {
            $statuses = $this->statuses;
            $query->where(function (Builder $inner) use ($statuses) {
                $inner->whereIn('calls.status', $statuses)
                    ->orWhereIn('voip_call_logs.status', $statuses);
            });
        }

        if ($this->direction !== null) {
            $direction = $this->direction;
            $query->where(function (Builder $inner) use ($direction) {
                $inner->where('calls.direction', $direction)
                    ->orWhere('voip_call_logs.direction', $direction);
            });
        }

        if ($this->minDurationSeconds !== null) {
            $min = $this->minDurationSeconds;
            $query->whereRaw('COALESCE(calls.duration_seconds, voip_call_logs.duration, 0) >= ?', [$min]);
        }

        if ($this->maxDurationSeconds !== null) {
            $max = $this->maxDurationSeconds;
            $query->whereRaw('COALESCE(calls.duration_seconds, voip_call_logs.duration, 0) <= ?', [$max]);
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('conversation_analyses.summary', 'like', $term)
                    ->orWhere('calls.customer_name', 'like', $term)
                    ->orWhere('calls.customer_phone', 'like', $term)
                    ->orWhere('organization_user.first_name', 'like', $term)
                    ->orWhere('organization_user.last_name', 'like', $term);
            });
        }

        return $query;
    }

    /** @param  Builder<\App\Models\ConversationAnalysis>  $query */
    public function applySort(Builder $query): Builder
    {
        $direction = $this->sortDir;

        return match ($this->sortBy) {
            'duration' => $query->orderByRaw('COALESCE(calls.duration_seconds, voip_call_logs.duration, 0) '.$direction),
            'agent' => $query->orderBy('organization_user.first_name', $direction)
                ->orderBy('organization_user.last_name', $direction),
            'status' => $query->orderByRaw('COALESCE(calls.status, voip_call_logs.status) '.$direction),
            'score' => $query->orderBy('conversation_analyses.score', $direction),
            default => $query->orderBy('conversation_analyses.analyzed_at', $direction),
        };
    }
}
