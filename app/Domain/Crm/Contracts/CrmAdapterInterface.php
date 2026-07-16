<?php

namespace App\Domain\Crm\Contracts;

use App\Domain\Crm\DTOs\CallIntelligenceSyncData;
use App\Domain\Crm\DTOs\ContactData;
use App\Domain\Crm\DTOs\CrmConnectionConfig;
use App\Domain\Crm\DTOs\LeadData;
use App\Domain\Crm\DTOs\SyncData;
use App\Domain\Crm\DTOs\TaskData;
use App\Domain\Crm\Enums\CrmProviderCode;
use App\Domain\Crm\ValueObjects\CrmOperationResult;

interface CrmAdapterInterface
{
    public function getProviderCode(): CrmProviderCode;

    public function configure(CrmConnectionConfig $config): void;

    public function testConnection(): CrmOperationResult;

    public function createLead(LeadData $lead): CrmOperationResult;

    public function updateLead(string $externalId, LeadData $lead): CrmOperationResult;

    public function getLead(string $externalId): CrmOperationResult;

    public function createContact(ContactData $contact): CrmOperationResult;

    public function createTask(TaskData $task): CrmOperationResult;

    public function sync(SyncData $sync): CrmOperationResult;

    public function listPipelines(): CrmOperationResult;

    public function listUsers(): CrmOperationResult;

    public function syncCallIntelligence(CallIntelligenceSyncData $data): CrmOperationResult;
}
