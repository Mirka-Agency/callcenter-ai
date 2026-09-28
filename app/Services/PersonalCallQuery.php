<?php

namespace App\Services;

use App\Models\ConversationAnalysis;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PersonalCallQuery
{
    /**
     * Personal calls for one expert in the same window as their call list.
     *
     * @return array{items: Collection<int, ConversationAnalysis>, total: int}
     */
    public function forEmployee(
        int $organizationId,
        int $employeeId,
        CarbonInterface $from,
        CarbonInterface $to,
        int $limit = 30,
    ): array {
        $query = ConversationAnalysis::query()
            ->personal()
            ->where('conversation_analyses.organization_id', $organizationId)
            ->where('conversation_analyses.organization_user_id', $employeeId)
            ->whereBetween('conversation_analyses.analyzed_at', [$from, $to]);

        $total = (clone $query)->count();

        $items = (clone $query)
            ->with(['call', 'callLog'])
            ->latest('conversation_analyses.analyzed_at')
            ->limit($limit)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
