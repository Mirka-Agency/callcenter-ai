<?php

namespace App\Infrastructure\Crm\Adapters;

use App\Domain\Crm\DTOs\ContactData;
use App\Domain\Crm\DTOs\CrmPipelineOption;
use App\Domain\Crm\DTOs\CrmUserOption;
use App\Domain\Crm\DTOs\LeadData;
use App\Domain\Crm\DTOs\SyncData;
use App\Domain\Crm\DTOs\TaskData;
use App\Domain\Crm\Enums\CrmProviderCode;
use App\Domain\Crm\ValueObjects\CrmOperationResult;
use App\Infrastructure\Crm\Clients\DidarApiClient;

class DidarCrmAdapter extends AbstractCrmAdapter
{
    private const DEAL_PIPELINES_ENDPOINT = 'pipeline/list/0';

    private const USERS_ENDPOINT = 'User/List';

    private ?DidarApiClient $client = null;

    public function getProviderCode(): CrmProviderCode
    {
        return CrmProviderCode::Didar;
    }

    public function testConnection(): CrmOperationResult
    {
        $response = $this->client()->post('contact/search', [
            'Criteria' => [],
            'From' => 0,
            'Limit' => 1,
        ]);

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('Message') ?? $response->json('Error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return $this->parseResponse($response->json() ?? [], 'Didar CRM connection successful.');
    }

    public function createLead(LeadData $lead): CrmOperationResult
    {
        $response = $this->client()->post('deal/save', $this->mapLeadPayload($lead));

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('Message') ?? $response->json('Error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return $this->parseResponse($response->json() ?? [], 'Lead created in Didar CRM.');
    }

    public function updateLead(string $externalId, LeadData $lead): CrmOperationResult
    {
        $payload = $this->mapLeadPayload($lead);
        $payload['Deal']['Id'] = $externalId;

        $response = $this->client()->post('deal/save', $payload);

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('Message') ?? $response->json('Error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return $this->parseResponse($response->json() ?? [], 'Lead updated in Didar CRM.');
    }

    public function getLead(string $externalId): CrmOperationResult
    {
        $response = $this->client()->post('deal/search', [
            'Criteria' => ['Id' => $externalId],
            'From' => 0,
            'Limit' => 1,
        ]);

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('Message') ?? $response->json('Error') ?? $response->body(),
                data: $response->json(),
            );
        }

        $body = $response->json() ?? [];
        $list = $body['Response']['List'] ?? $body['Response'] ?? [];

        if (empty($list)) {
            return CrmOperationResult::failure('Lead not found in Didar CRM.', data: $body);
        }

        $item = is_array($list) && isset($list[0]) ? $list[0] : $list;

        return CrmOperationResult::success(
            externalId: (string) ($item['Id'] ?? $externalId),
            data: $item,
            message: 'Lead retrieved from Didar CRM.',
        );
    }

    public function createContact(ContactData $contact): CrmOperationResult
    {
        $response = $this->client()->post('contact/save', $this->mapContactPayload($contact));

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('Message') ?? $response->json('Error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return $this->parseResponse($response->json() ?? [], 'Contact created in Didar CRM.');
    }

    public function createTask(TaskData $task): CrmOperationResult
    {
        $activity = array_filter([
            'Title' => $task->title,
            'Description' => $task->description,
            'DueDate' => $task->dueAt,
            'RelatedId' => $task->relatedExternalId,
            'DealId' => $task->relatedExternalId,
            'Assignee' => $task->assignee,
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->client()->post('activity/save', [
            'Activity' => $activity,
            'SetDone' => false,
        ]);

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('Message') ?? $response->json('Error') ?? $response->body(),
                data: $response->json(),
            );
        }

        return $this->parseResponse($response->json() ?? [], 'Task created in Didar CRM.');
    }

    public function sync(SyncData $sync): CrmOperationResult
    {
        $endpoint = match ($sync->entity) {
            'contacts' => 'contact/search',
            'leads', 'deals' => 'deal/search',
            default => 'contact/search',
        };

        $response = $this->client()->post($endpoint, [
            'Criteria' => $sync->filters,
            'From' => 0,
            'Limit' => 100,
        ]);

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('Message') ?? $response->json('Error') ?? $response->body(),
                data: $response->json(),
            );
        }

        $body = $response->json() ?? [];
        $totalCount = $body['Response']['TotalCount'] ?? count($body['Response']['List'] ?? []);

        return CrmOperationResult::success(
            data: $body['Response'] ?? $body,
            message: "Synced {$totalCount} records from Didar CRM.",
        );
    }

    public function listPipelines(): CrmOperationResult
    {
        $response = $this->client()->post(self::DEAL_PIPELINES_ENDPOINT);

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('Message') ?? $response->json('Error') ?? $response->body(),
                data: $response->json(),
            );
        }

        $body = $response->json() ?? [];
        $rawList = $body['Response']['List'] ?? $body['Response'] ?? [];

        if (! is_array($rawList)) {
            return CrmOperationResult::failure('Unexpected pipeline response from Didar CRM.', data: $body);
        }

        if (isset($rawList['Id']) || isset($rawList['Title'])) {
            $rawList = [$rawList];
        }

        $pipelines = [];
        foreach ($rawList as $item) {
            if (! is_array($item)) {
                continue;
            }

            $pipeline = CrmPipelineOption::fromArray($item);
            if ($pipeline->id === '') {
                continue;
            }

            $pipelines[] = $pipeline->toArray();
        }

        return CrmOperationResult::success(
            data: ['pipelines' => $pipelines],
            message: 'Pipelines loaded from Didar CRM.',
        );
    }

    public function listUsers(): CrmOperationResult
    {
        $response = $this->client()->post(self::USERS_ENDPOINT);

        if ($response->failed()) {
            return $this->parseHttpFailure(
                message: $response->json('Message') ?? $response->json('Error') ?? $response->body(),
                data: $response->json(),
            );
        }

        $body = $response->json() ?? [];
        $rawList = $body['Response']['List'] ?? $body['Response'] ?? [];

        if (! is_array($rawList)) {
            return CrmOperationResult::failure('Unexpected user response from Didar CRM.', data: $body);
        }

        if (isset($rawList['Id']) || isset($rawList['UserId'])) {
            $rawList = [$rawList];
        }

        $users = [];
        foreach ($rawList as $item) {
            if (! is_array($item)) {
                continue;
            }

            $user = CrmUserOption::fromArray($item);
            if ($user->id === '') {
                continue;
            }

            $users[] = $user->toArray();
        }

        return CrmOperationResult::success(
            data: ['users' => $users],
            message: 'Users loaded from Didar CRM.',
        );
    }

    private function client(): DidarApiClient
    {
        return $this->client ??= new DidarApiClient(
            credentials: $this->config->credentials,
            settings: $this->config->settings,
        );
    }

    private function mapLeadPayload(LeadData $lead): array
    {
        $lead = $lead->withDealDefaults(
            pipelineStageId: $this->config->settings->pipelineStageId,
            ownerId: $this->config->settings->dealOwnerId,
        );

        $deal = array_filter([
            'Title' => $lead->title,
            'FirstName' => $lead->firstName,
            'LastName' => $lead->lastName,
            'Email' => $lead->email,
            'MobilePhone' => $lead->phone,
            'CompanyName' => $lead->company,
            'Description' => $lead->description,
            'Source' => $lead->source,
            'ContactId' => $lead->contactId,
            'PipelineStageId' => $lead->pipelineStageId,
            'OwnerId' => $lead->ownerId,
            'Fields' => $lead->customFields ?: null,
        ], fn ($value) => $value !== null && $value !== '');

        return ['Deal' => $deal];
    }

    private function mapContactPayload(ContactData $contact): array
    {
        return array_filter([
            'FirstName' => $contact->firstName,
            'LastName' => $contact->lastName,
            'Email' => $contact->email,
            'MobilePhone' => $contact->phone,
            'CompanyName' => $contact->company,
            'Fields' => $contact->customFields ?: null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
