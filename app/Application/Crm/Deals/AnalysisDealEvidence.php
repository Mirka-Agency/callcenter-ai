<?php

namespace App\Application\Crm\Deals;

use App\Models\ConversationAnalysis;

class AnalysisDealEvidence
{
    /** @return array<string, mixed> */
    public static function from(ConversationAnalysis $analysis): array
    {
        $lead = is_array($analysis->lead_quality_json) ? $analysis->lead_quality_json : [];
        $insights = is_array($analysis->customer_insights_json) ? $analysis->customer_insights_json : [];

        return [
            'summary' => $analysis->summary,
            'lead_score' => isset($lead['score']) && is_numeric($lead['score']) ? (int) $lead['score'] : null,
            'lead_level' => $lead['level'] ?? null,
            'purchase_intent' => $insights['purchase_probability'] ?? $insights['intent'] ?? null,
            'concerns' => $analysis->concerns_json,
            'sentiment' => $analysis->sentiment?->value ?? $analysis->sentiment,
            'next_actions' => $analysis->next_actions_json,
            'amount_inferred' => false,
            'stage_change_allowed' => false,
        ];
    }
}
