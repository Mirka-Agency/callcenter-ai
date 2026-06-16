<?php

namespace App\Services;

use App\Domain\Call\Enums\ConversationSource;
use App\DTOs\ReportFilter;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\OrganizationUser;
use App\Services\Reports\CallMetricsAnalytics;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EmployeeActivityAnalytics
{
    public function __construct(private CallMetricsAnalytics $callMetrics) {}

    /** @return array<string, mixed> */
    public function summary(ReportFilter $filter, OrganizationUser $employee): array
    {
        $analyses = $this->analysisQuery($filter, $employee);
        $uploads = $this->uploadQuery($filter, $employee);
        $previous = $filter->previousPeriod();

        $analyzedCount = (clone $analyses)->count();
        $uploadCount = (clone $uploads)->count();
        $feedbackCount = (clone $analyses)->whereNotNull('overall_evaluation')->count();
        $previousAnalyzed = $this->analysisQuery($previous, $employee)->count();

        $lastAnalysisAt = (clone $analyses)->max('analyzed_at');
        $lastUploadAt = (clone $uploads)->max('created_at');
        $lastActivityAt = collect([$lastAnalysisAt, $lastUploadAt])
            ->filter()
            ->map(fn ($value) => Carbon::parse($value))
            ->sortDesc()
            ->first();

        return [
            'analyzed_count' => $analyzedCount,
            'upload_count' => $uploadCount,
            'feedback_count' => $feedbackCount,
            'total_events' => $analyzedCount + $uploadCount,
            'average_score' => round((float) (clone $analyses)->avg('score'), 1),
            'analyzed_delta' => $analyzedCount - $previousAnalyzed,
            'last_activity' => $lastActivityAt ? JalaliDate::ago($lastActivityAt) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function volumeTrend(ReportFilter $filter, OrganizationUser $employee): array
    {
        $from = $filter->from->copy()->startOfDay();
        $to = $filter->to->copy()->endOfDay();
        $days = max(1, $from->diffInDays($to) + 1);

        $analysisGroups = $this->analysisQuery($filter, $employee)
            ->get(['analyzed_at'])
            ->groupBy(fn (ConversationAnalysis $analysis) => $analysis->analyzed_at->format('Y-m-d'));

        $uploadGroups = $this->uploadQuery($filter, $employee)
            ->get(['created_at'])
            ->groupBy(fn (Call $call) => $call->created_at->format('Y-m-d'));

        $series = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $from->copy()->addDays($offset);
            $key = $date->format('Y-m-d');
            $analysisCount = $analysisGroups->get($key, collect())->count();
            $uploadCount = $uploadGroups->get($key, collect())->count();

            $series[] = [
                'label' => JalaliDate::monthDay($key),
                'analyses' => $analysisCount,
                'uploads' => $uploadCount,
                'total' => $analysisCount + $uploadCount,
            ];
        }

        return $series;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function timeline(
        ReportFilter $filter,
        OrganizationUser $employee,
        string $type = 'all',
        ?string $search = null,
        int $limit = 40,
    ): array {
        $events = collect();

        if (in_array($type, ['all', 'analysis', 'feedback'], true)) {
            $analysisQuery = $this->analysisQuery($filter, $employee)
                ->with(['call:id,customer_id,customer_name,caller_number,duration_seconds,title', 'call.customer:id,name,company_name,phone_number'])
                ->latest('analyzed_at');

            if ($type === 'feedback') {
                $analysisQuery->whereNotNull('overall_evaluation');
            }

            $events = $events->merge(
                $analysisQuery
                    ->limit($limit)
                    ->get()
                    ->map(fn (ConversationAnalysis $analysis) => $this->mapAnalysisEvent($analysis, $type === 'feedback'))
            );
        }

        if (in_array($type, ['all', 'upload'], true)) {
            $events = $events->merge(
                $this->uploadQuery($filter, $employee)
                    ->with('latestAnalysis:id,call_id')
                    ->whereDoesntHave('latestAnalysis')
                    ->latest('created_at')
                    ->limit($limit)
                    ->get()
                    ->map(fn (Call $call) => $this->mapUploadEvent($call))
            );
        }

        if ($search !== null && trim($search) !== '') {
            $needle = mb_strtolower(trim($search));
            $events = $events->filter(function (array $event) use ($needle) {
                $haystack = mb_strtolower(implode(' ', [
                    $event['title'] ?? '',
                    $event['description'] ?? '',
                    $event['customer'] ?? '',
                ]));

                return str_contains($haystack, $needle);
            });
        }

        return $events
            ->sortByDesc('timestamp')
            ->take($limit)
            ->values()
            ->map(function (array $event) {
                unset($event['timestamp']);

                return $event;
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentFeedback(ReportFilter $filter, OrganizationUser $employee, int $limit = 6): array
    {
        return $this->analysisQuery($filter, $employee)
            ->whereNotNull('overall_evaluation')
            ->with(['call:id,customer_id,customer_name,caller_number', 'call.customer:id,name,company_name,phone_number'])
            ->latest('analyzed_at')
            ->limit($limit)
            ->get()
            ->map(function (ConversationAnalysis $analysis) {
                $call = $analysis->call;

                return [
                    'analysis_id' => $analysis->id,
                    'score' => $analysis->score,
                    'customer' => $call?->customer?->displayName()
                        ?? $call?->customer_name
                        ?? $call?->caller_number
                        ?? '—',
                    'feedback' => $analysis->overall_evaluation,
                    'time' => JalaliDate::ago($analysis->analyzed_at),
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function followUps(ReportFilter $filter, OrganizationUser $employee, int $limit = 8): array
    {
        return $this->analysisQuery($filter, $employee)
            ->latest('analyzed_at')
            ->limit(20)
            ->get()
            ->flatMap(fn (ConversationAnalysis $analysis) => collect($analysis->next_actions_json ?? [])->map(fn ($action) => [
                'action' => is_string($action) ? $action : ($action['action'] ?? $action['title'] ?? 'پیگیری'),
                'time' => JalaliDate::ago($analysis->analyzed_at),
                'analysis_id' => $analysis->id,
            ]))
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return Builder<ConversationAnalysis> */
    private function analysisQuery(ReportFilter $filter, OrganizationUser $employee): Builder
    {
        return ConversationAnalysis::query()
            ->where('organization_id', $filter->organizationId)
            ->where('organization_user_id', $employee->id)
            ->whereBetween('analyzed_at', [$filter->from, $filter->to]);
    }

    /** @return Builder<Call> */
    private function uploadQuery(ReportFilter $filter, OrganizationUser $employee): Builder
    {
        return Call::query()
            ->where('organization_id', $filter->organizationId)
            ->where('organization_user_id', $employee->id)
            ->where('source', ConversationSource::ManualUpload)
            ->whereBetween('created_at', [$filter->from, $filter->to]);
    }

    /** @return array<string, mixed> */
    private function mapAnalysisEvent(ConversationAnalysis $analysis, bool $asFeedback): array
    {
        $call = $analysis->call;
        $customer = $call?->customer?->displayName()
            ?? $call?->customer_name
            ?? $call?->caller_number
            ?? 'مکالمه';

        $description = $asFeedback
            ? ($analysis->overall_evaluation ?? $analysis->summary)
            : $analysis->summary;

        return [
            'type' => $asFeedback ? 'feedback' : 'analysis',
            'timestamp' => $analysis->analyzed_at,
            'time' => JalaliDate::ago($analysis->analyzed_at),
            'title' => $asFeedback ? 'بازخورد هوش مصنوعی' : 'تحلیل تماس تکمیل شد',
            'customer' => $customer,
            'description' => $description,
            'score' => $analysis->score,
            'sentiment' => $analysis->sentiment?->label(),
            'duration_label' => $this->callMetrics->formatDuration($call?->duration_seconds ?? 0),
            'url' => route('employee.calls.show', $analysis),
        ];
    }

    /** @return array<string, mixed> */
    private function mapUploadEvent(Call $call): array
    {
        $status = $call->processing_status?->label() ?? 'در صف';

        return [
            'type' => 'upload',
            'timestamp' => $call->created_at,
            'time' => JalaliDate::ago($call->created_at),
            'title' => 'آپلود تماس جدید',
            'customer' => $call->displayTitle(),
            'description' => "وضعیت: {$status}",
            'score' => null,
            'sentiment' => null,
            'duration_label' => $this->callMetrics->formatDuration($call->duration_seconds ?? 0),
            'url' => route('employee.uploads.show', $call),
        ];
    }
}
