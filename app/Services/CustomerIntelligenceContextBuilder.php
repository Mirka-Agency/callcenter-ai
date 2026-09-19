<?php

namespace App\Services;

use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\CrmPipelineSync;
use App\Models\Organization;
use App\Support\CustomerNextActionAggregator;
use Illuminate\Support\Str;

class CustomerIntelligenceContextBuilder
{
    public function build(int $organizationId, string $phone, ?string $customerName = null): array
    {
        $normalizedPhone = $this->normalizePhone($phone);

        $calls = Call::query()
            ->where('organization_id', $organizationId)
            ->where(function ($query) use ($phone, $normalizedPhone) {
                $query->where('caller_number', $phone)
                    ->orWhere('receiver_number', $phone)
                    ->orWhere('customer_phone', $phone)
                    ->when($normalizedPhone !== $phone, fn ($q) => $q
                        ->orWhere('caller_number', 'like', "%{$normalizedPhone}%")
                        ->orWhere('customer_phone', 'like', "%{$normalizedPhone}%"));
            })
            ->orderByDesc('started_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $callIds = $calls->pluck('id');

        $analyses = ConversationAnalysis::query()
            ->where('organization_id', $organizationId)
            ->when($callIds->isNotEmpty(), fn ($q) => $q->whereIn('call_id', $callIds), fn ($q) => $q->whereRaw('0 = 1'))
            ->latest('analyzed_at')
            ->limit(10)
            ->get();

        $resolvedName = $customerName
            ?? $calls->firstWhere('customer_name')?->customer_name
            ?? null;

        $lastContact = $calls->first()?->started_at ?? $calls->first()?->created_at;

        $recommendedActions = $this->collectRecommendedActions($analyses);
        $recentActions = $this->collectRecentActions($analyses, $calls, $organizationId);
        $timeline = $this->buildTimeline($calls, $analyses);
        $summary = $this->buildSummary($organizationId, $phone, $calls, $analyses, $resolvedName);

        return [
            'customer_name' => $resolvedName,
            'customer_phone' => $phone,
            'customer_context' => [
                'last_contact_date' => $lastContact?->toDateString(),
                'total_calls' => $calls->count(),
                'total_analyses' => $analyses->count(),
                'summary' => $summary,
            ],
            'recommended_actions' => $recommendedActions,
            'recent_actions' => $recentActions,
            'timeline' => $timeline,
        ];
    }

    /** @return list<string> */
    private function collectRecommendedActions($analyses): array
    {
        return CustomerNextActionAggregator::prioritized($analyses);
    }

    /** @return list<array{action: string, date: ?string}> */
    private function collectRecentActions($analyses, $calls, int $organizationId): array
    {
        $items = [];

        foreach ($analyses as $analysis) {
            foreach ($analysis->next_actions_json ?? [] as $action) {
                $text = is_string($action) ? $action : ($action['action'] ?? $action['title'] ?? null);
                if ($text) {
                    $items[] = [
                        'action' => $text,
                        'date' => $analysis->analyzed_at?->toDateString(),
                        'sort' => $analysis->analyzed_at?->timestamp ?? 0,
                    ];
                }
            }
        }

        $syncs = CrmPipelineSync::query()
            ->where('organization_id', $organizationId)
            ->whereIn('conversation_analysis_id', $analyses->pluck('id'))
            ->latest('created_at')
            ->limit(10)
            ->get();

        foreach ($syncs as $sync) {
            if ($sync->status === 'completed') {
                $items[] = [
                    'action' => ($sync->action_type ?? 'CRM').' synced to CRM',
                    'date' => $sync->created_at?->toDateString(),
                    'sort' => $sync->created_at?->timestamp ?? 0,
                ];
            }
        }

        foreach ($calls as $call) {
            if ($call->notes) {
                $items[] = [
                    'action' => Str::limit($call->notes, 80),
                    'date' => ($call->started_at ?? $call->created_at)?->toDateString(),
                    'sort' => ($call->started_at ?? $call->created_at)?->timestamp ?? 0,
                ];
            }
        }

        usort($items, fn ($a, $b) => $b['sort'] <=> $a['sort']);

        return array_slice(array_map(fn ($item) => [
            'action' => $item['action'],
            'date' => $item['date'],
        ], $items), 0, 5);
    }

    /** @return list<array{type: string, title: string, date: ?string}> */
    private function buildTimeline($calls, $analyses): array
    {
        $timeline = [];

        foreach ($calls as $call) {
            $timeline[] = [
                'type' => 'call',
                'title' => ($call->direction ?? 'call').' call'.($call->duration_seconds ? " ({$call->duration_seconds}s)" : ''),
                'date' => ($call->started_at ?? $call->created_at)?->toDateString(),
                'sort' => ($call->started_at ?? $call->created_at)?->timestamp ?? 0,
            ];
            if ($call->notes) {
                $timeline[] = [
                    'type' => 'note',
                    'title' => Str::limit($call->notes, 100),
                    'date' => ($call->started_at ?? $call->created_at)?->toDateString(),
                    'sort' => ($call->started_at ?? $call->created_at)?->timestamp ?? 0,
                ];
            }
        }

        foreach ($analyses as $analysis) {
            foreach ($analysis->next_actions_json ?? [] as $action) {
                $text = is_string($action) ? $action : ($action['action'] ?? null);
                if ($text) {
                    $timeline[] = [
                        'type' => 'follow_up',
                        'title' => $text,
                        'date' => $analysis->analyzed_at?->toDateString(),
                        'sort' => $analysis->analyzed_at?->timestamp ?? 0,
                    ];
                }
            }
        }

        usort($timeline, fn ($a, $b) => $b['sort'] <=> $a['sort']);

        return array_slice(array_map(fn ($item) => [
            'type' => $item['type'],
            'title' => $item['title'],
            'date' => $item['date'],
        ], $timeline), 0, 12);
    }

    private function buildSummary(int $organizationId, string $phone, $calls, $analyses, ?string $name): string
    {
        $org = Organization::query()->find($organizationId);
        $callCount = $calls->count();
        $recentCount = $calls->filter(fn ($c) => ($c->started_at ?? $c->created_at)?->gte(now()->subMonth()))->count();

        $parts = [];

        if ($name) {
            $parts[] = "{$name} ({$phone})";
        } else {
            $parts[] = "Customer {$phone}";
        }

        if ($callCount === 0) {
            $parts[] = 'is a new contact with no previous interactions recorded.';

            return implode(' ', $parts);
        }

        $parts[] = "has spoken with {$org?->title} {$callCount} time".($callCount === 1 ? '' : 's');
        if ($recentCount > 0) {
            $parts[] = "({$recentCount} in the last month).";
        } else {
            $parts[] = 'in total.';
        }

        $topics = [];
        foreach ($analyses->take(3) as $analysis) {
            if ($analysis->summary) {
                $topics[] = Str::limit($analysis->summary, 120);
            }
        }

        if (! empty($topics)) {
            $parts[] = 'Recent context: '.implode(' ', array_slice($topics, 0, 2));
        }

        $pending = $this->collectRecommendedActions($analyses);
        if (! empty($pending)) {
            $parts[] = 'Pending: '.Str::limit($pending[0], 80);
        }

        return implode(' ', $parts);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }
}
