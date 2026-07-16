<?php

namespace App\Infrastructure\Crm\Adapters;

use App\Domain\Crm\Contracts\CrmAdapterInterface;
use App\Domain\Crm\DTOs\CallIntelligenceSyncData;
use App\Domain\Crm\DTOs\ContactData;
use App\Domain\Crm\DTOs\CrmConnectionConfig;
use App\Domain\Crm\DTOs\LeadData;
use App\Domain\Crm\DTOs\SyncData;
use App\Domain\Crm\DTOs\TaskData;
use App\Domain\Crm\Enums\CrmProviderCode;
use App\Domain\Crm\ValueObjects\CrmOperationResult;

abstract class AbstractCrmAdapter implements CrmAdapterInterface
{
    protected CrmConnectionConfig $config;

    public function configure(CrmConnectionConfig $config): void
    {
        $this->config = $config;
    }

    protected function parseResponse(array $body, string $successMessage): CrmOperationResult
    {
        if (isset($body['Error']) || isset($body['Message']) && isset($body['Code'])) {
            return CrmOperationResult::failure(
                error: (string) ($body['Error'] ?? $body['Message'] ?? 'Unknown CRM error'),
                data: $body,
            );
        }

        $response = $body['Response'] ?? $body;
        $externalId = $response['Id'] ?? $response['id'] ?? null;

        return CrmOperationResult::success(
            externalId: $externalId ? (string) $externalId : null,
            data: is_array($response) ? $response : ['response' => $response],
            message: $successMessage,
        );
    }

    protected function parseHttpFailure(string $message, ?array $data = null): CrmOperationResult
    {
        return CrmOperationResult::failure(error: $message, data: $data);
    }

    public function listPipelines(): CrmOperationResult
    {
        return CrmOperationResult::failure('Listing pipelines is not supported by this CRM provider.');
    }

    public function listUsers(): CrmOperationResult
    {
        return CrmOperationResult::failure('Listing users is not supported by this CRM provider.');
    }

    public function syncCallIntelligence(CallIntelligenceSyncData $data): CrmOperationResult
    {
        $results = [];
        $leadScore = (int) ($data->leadQuality['score'] ?? 0);
        $leadLevel = (string) ($data->leadQuality['level'] ?? 'low');
        $priorityLabel = match ($leadLevel) {
            'high' => 'لید بالا',
            'medium' => 'لید متوسط',
            default => 'لید کم',
        };
        $dueAt = match (true) {
            $leadScore >= 70 => now()->addHours(4)->toIso8601String(),
            $leadScore >= 40 => now()->addDay()->toIso8601String(),
            default => now()->addDays(3)->toIso8601String(),
        };

        $leadSummary = $data->leadQuality['reason'] ?? null;
        $customerPerson = trim((string) ($data->customerIdentity['person_name'] ?? ''));
        $customerCompany = trim((string) ($data->customerIdentity['company_name'] ?? ''));
        $customerIdentityLine = match (true) {
            $customerPerson !== '' && $customerCompany !== '' => "هویت مشتری: {$customerPerson} — {$customerCompany}",
            $customerPerson !== '' => "هویت مشتری: {$customerPerson}",
            $customerCompany !== '' => "شرکت مشتری: {$customerCompany}",
            default => null,
        };
        $concernLines = array_map(
            fn (array $concern) => sprintf(
                '- [%s/%s] %s',
                $concern['type'] ?? 'other',
                $concern['severity'] ?? 'medium',
                $concern['text'] ?? '',
            ),
            $data->concerns ?? [],
        );
        $descriptionParts = array_filter([
            $data->summary,
            "امتیاز تماس: {$data->score} | احساس: {$data->sentiment}",
            $customerIdentityLine,
            $leadSummary ? "کیفیت لید ({$leadScore}/{$leadLevel}): {$leadSummary}" : null,
            $concernLines !== [] ? "دغدغه‌های مشتری:\n".implode("\n", $concernLines) : null,
        ]);
        $baseDescription = implode("\n\n", $descriptionParts);

        $metadata = [
            'customer_phone' => $data->customerPhone,
            'analysis_id' => $data->analysisId,
            'lead_quality' => $data->leadQuality,
            'concerns' => $data->concerns,
            'customer_identity' => $data->customerIdentity,
            'sales_priority' => $leadScore,
        ];

        $dealExternalId = null;
        $settings = $this->config->settings;

        if ($settings->hasDealDefaults()) {
            $dealTitle = match (true) {
                $customerPerson !== '' => "[{$priorityLabel}] {$customerPerson}",
                $customerCompany !== '' => "[{$priorityLabel}] {$customerCompany}",
                filled($data->customerPhone) => "[{$priorityLabel}] {$data->customerPhone}",
                default => "[{$priorityLabel}] هوش تماس #{$data->analysisId}",
            };

            $dealResult = $this->createLead(new LeadData(
                title: $dealTitle,
                firstName: $customerPerson !== '' ? $customerPerson : null,
                phone: $data->customerPhone,
                company: $customerCompany !== '' ? $customerCompany : null,
                description: $baseDescription,
                source: 'call_intelligence',
                pipelineStageId: $settings->pipelineStageId,
                ownerId: $settings->dealOwnerId,
                metadata: $metadata,
            ));

            $results[] = $dealResult->success ? 'deal_created' : 'deal_failed';
            $dealExternalId = $dealResult->externalId;
        }

        foreach ($data->nextActions as $action) {
            $task = new TaskData(
                title: "[{$priorityLabel}] پیگیری: {$action}",
                description: $baseDescription,
                dueAt: $dueAt,
                relatedExternalId: $dealExternalId,
                metadata: $metadata,
            );

            $result = $this->createTask($task);
            $results[] = $result->success ? 'task_created' : 'task_failed';
        }

        if ($data->nextActions === [] && $dealExternalId === null) {
            $task = new TaskData(
                title: "[{$priorityLabel}] خلاصه هوش تماس",
                description: $baseDescription,
                dueAt: $dueAt,
                metadata: $metadata,
            );
            $this->createTask($task);
            $results[] = 'task_created';
        }

        return CrmOperationResult::success(
            externalId: $dealExternalId,
            data: [
                'actions' => $results,
                'analysis_id' => $data->analysisId,
                'lead_score' => $leadScore,
                'lead_level' => $leadLevel,
                'concerns_count' => count($data->concerns ?? []),
                'deal_id' => $dealExternalId,
            ],
            message: 'Call intelligence synced to CRM.',
        );
    }
}
