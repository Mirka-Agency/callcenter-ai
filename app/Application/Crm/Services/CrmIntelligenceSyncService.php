<?php

namespace App\Application\Crm\Services;

use App\Application\Crm\CrmManager;
use App\Domain\Crm\DTOs\CallIntelligenceSyncData;
use App\Models\ConversationAnalysis;
use App\Models\CrmPipelineSync;
use App\Models\Organization;

class CrmIntelligenceSyncService
{
    public function syncAnalysis(ConversationAnalysis $analysis): int
    {
        $organization = Organization::query()->find($analysis->organization_id);

        if (! $organization?->usesExternalCrm()) {
            return 0;
        }

        $connection = $organization->crmConnections()->where('is_active', true)->orderByDesc('is_default')->first();

        if (! $connection) {
            return 0;
        }

        $call = $analysis->call;
        $customerPhone = $call?->customer_phone
            ?? $call?->caller_number
            ?? $call?->receiver_number;

        $data = new CallIntelligenceSyncData(
            organizationId: $analysis->organization_id,
            connectionId: $connection->id,
            analysisId: $analysis->id,
            callId: $analysis->call_id ?? $analysis->voip_call_log_id ?? 0,
            organizationUserId: $analysis->organization_user_id,
            summary: $analysis->summary,
            score: $analysis->score,
            sentiment: $analysis->sentiment->value,
            strengths: $analysis->strengths_json ?? [],
            weaknesses: $analysis->weaknesses_json ?? [],
            nextActions: $analysis->next_actions_json ?? [],
            customerPhone: $customerPhone,
            customerInsights: $analysis->customer_insights_json,
            operationalInsights: $analysis->operational_insights_json,
            leadQuality: $analysis->lead_quality_json,
            concerns: $analysis->concerns_json ?? [],
            customerIdentity: $analysis->customer_identity_json,
        );

        $result = CrmManager::forOrganization($analysis->organization_id)
            ->connection($connection->id)
            ->syncCallIntelligence($data);

        CrmPipelineSync::query()->create([
            'organization_id' => $analysis->organization_id,
            'pipeline_execution_id' => null,
            'conversation_analysis_id' => $analysis->id,
            'organization_crm_connection_id' => $connection->id,
            'provider_code' => $connection->provider->code,
            'action_type' => 'sync_call_intelligence',
            'status' => $result->success ? 'completed' : 'failed',
            'external_id' => $result->externalId,
            'payload' => $result->data,
            'error_message' => $result->error,
        ]);

        return $result->success ? 1 : 0;
    }
}
