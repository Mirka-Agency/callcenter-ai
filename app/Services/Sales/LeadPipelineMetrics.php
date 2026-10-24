<?php

namespace App\Services\Sales;

use App\Domain\Platform\Enums\LeadStatus;
use App\Filament\Resources\ConsultationRequests\ConsultationRequestResource;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\DemoRequests\DemoRequestResource;
use App\Models\ConsultationRequest;
use App\Models\ContactMessage;
use App\Models\DemoRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LeadPipelineMetrics
{
    /** @return array<string, array{model: class-string<Model>, resource: class-string}> */
    public static function types(): array
    {
        return [
            'demo' => ['model' => DemoRequest::class, 'resource' => DemoRequestResource::class],
            'consultation' => ['model' => ConsultationRequest::class, 'resource' => ConsultationRequestResource::class],
            'contact' => ['model' => ContactMessage::class, 'resource' => ContactMessageResource::class],
        ];
    }

    /** @return array<string, array<string, int>> count per type and status */
    public function statusBoard(): array
    {
        $board = [];

        foreach (self::types() as $type => ['model' => $model]) {
            $counts = $model::query()
                ->whereNull('archived_at')
                ->selectRaw('lead_status, COUNT(*) as aggregate')
                ->groupBy('lead_status')
                ->pluck('aggregate', 'lead_status');

            foreach (LeadStatus::cases() as $status) {
                $board[$type][$status->value] = (int) ($counts[$status->value] ?? 0);
            }
        }

        return $board;
    }

    /**
     * Leads and conversions per source over the last $days.
     *
     * @return list<array{source: string, leads: int, converted: int, rate: float|null}>
     */
    public function sources(int $days = 90): array
    {
        $rows = [];

        foreach (self::types() as ['model' => $model]) {
            $model::query()
                ->where('created_at', '>=', now()->subDays($days))
                ->selectRaw("COALESCE(source, 'direct') as source_key, COUNT(*) as leads")
                ->selectRaw("SUM(CASE WHEN lead_status = 'converted' THEN 1 ELSE 0 END) as converted")
                ->groupBy('source_key')
                ->toBase()
                ->get()
                ->each(function (object $row) use (&$rows): void {
                    $rows[$row->source_key]['leads'] = ($rows[$row->source_key]['leads'] ?? 0) + (int) $row->leads;
                    $rows[$row->source_key]['converted'] = ($rows[$row->source_key]['converted'] ?? 0) + (int) $row->converted;
                });
        }

        return collect($rows)
            ->map(fn (array $row, string $source): array => [
                'source' => $source,
                'leads' => $row['leads'],
                'converted' => $row['converted'],
                'rate' => $row['leads'] === 0 ? null : $row['converted'] / $row['leads'],
            ])
            ->sortByDesc('leads')
            ->values()
            ->all();
    }

    /**
     * Median hours to first contact over the last $days, and how many leads met the SLA.
     *
     * @return array{median_hours: float|null, within_sla: int, contacted: int, breaching_now: int}
     */
    public function responseTimes(int $days = 30): array
    {
        $hours = [];
        $breaching = 0;

        foreach (self::types() as ['model' => $model]) {
            $model::query()
                ->where('created_at', '>=', now()->subDays($days))
                ->whereNotNull('first_contacted_at')
                ->get(['created_at', 'first_contacted_at'])
                ->each(function (Model $lead) use (&$hours): void {
                    $hours[] = max(0, $lead->created_at->diffInMinutes($lead->first_contacted_at) / 60);
                });

            $breaching += $model::query()->breachingSla()->count();
        }

        sort($hours);
        $count = count($hours);

        return [
            'median_hours' => $count === 0 ? null : ($count % 2 ? $hours[intdiv($count, 2)] : ($hours[$count / 2 - 1] + $hours[$count / 2]) / 2),
            'within_sla' => count(array_filter($hours, fn (float $value): bool => $value <= DemoRequest::SLA_HOURS)),
            'contacted' => $count,
            'breaching_now' => $breaching,
        ];
    }

    /** @return Collection<int, array{type: string, name: string, phone: string|null, status: LeadStatus, follow_up_at: mixed, assignee: string|null, url: string}> */
    public function followUps(int $limit = 20): Collection
    {
        return collect(self::types())
            ->flatMap(fn (array $type, string $key) => $type['model']::query()
                ->followUpDue()
                ->whereNull('archived_at')
                ->with('assignee:id,name')
                ->orderBy('follow_up_at')
                ->limit($limit)
                ->get()
                ->map(fn (Model $lead): array => [
                    'type' => $key,
                    'name' => $lead->leadName(),
                    'phone' => $lead->phone ?? $lead->email ?? null,
                    'status' => $lead->lead_status ?? LeadStatus::New,
                    'follow_up_at' => $lead->follow_up_at,
                    'assignee' => $lead->assignee?->name,
                    'url' => $type['resource']::getUrl('view', ['record' => $lead]),
                ]))
            ->sortBy('follow_up_at')
            ->take($limit)
            ->values();
    }
}
