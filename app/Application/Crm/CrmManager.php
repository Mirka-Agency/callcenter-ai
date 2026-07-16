<?php

namespace App\Application\Crm;

use App\Application\Crm\Services\CrmConnectionResolver;
use App\Domain\Crm\Contracts\CrmAdapterInterface;
use App\Domain\Crm\Contracts\CrmLogRepositoryInterface;
use App\Domain\Crm\DTOs\CallIntelligenceSyncData;
use App\Domain\Crm\DTOs\ContactData;
use App\Domain\Crm\DTOs\CrmConnectionConfig;
use App\Domain\Crm\DTOs\LeadData;
use App\Domain\Crm\DTOs\SyncData;
use App\Domain\Crm\DTOs\TaskData;
use App\Domain\Crm\Enums\CrmOperation;
use App\Domain\Crm\Events\CrmConnectionTested;
use App\Domain\Crm\Events\CrmSyncCompleted;
use App\Domain\Crm\Exceptions\CrmConnectionNotFoundException;
use App\Domain\Crm\ValueObjects\CrmOperationResult;

class CrmManager
{
    private ?int $organizationId = null;

    private ?int $connectionId = null;

    public function __construct(
        private CrmConnectionResolver $resolver,
        private CrmLogRepositoryInterface $logs,
    ) {}

    public static function make(
        CrmConnectionResolver $resolver,
        CrmLogRepositoryInterface $logs,
    ): self {
        return new self($resolver, $logs);
    }

    public static function forOrganization(int $organizationId): self
    {
        $instance = new self(
            resolver: app(CrmConnectionResolver::class),
            logs: app(CrmLogRepositoryInterface::class),
        );
        $instance->organizationId = $organizationId;

        return $instance;
    }

    public function connection(?int $connectionId = null): self
    {
        $this->connectionId = $connectionId;

        return $this;
    }

    public function default(): self
    {
        $this->connectionId = null;

        return $this;
    }

    public function testConnection(): CrmOperationResult
    {
        return $this->execute(CrmOperation::TestConnection, fn (CrmAdapterInterface $adapter) => $adapter->testConnection());
    }

    public function createLead(LeadData|array $data): CrmOperationResult
    {
        $lead = $data instanceof LeadData ? $data : LeadData::fromArray($data);

        return $this->execute(CrmOperation::CreateLead, fn (CrmAdapterInterface $adapter) => $adapter->createLead($lead), $lead->toArray());
    }

    public function updateLead(string $externalId, LeadData|array $data): CrmOperationResult
    {
        $lead = $data instanceof LeadData ? $data : LeadData::fromArray($data);

        return $this->execute(CrmOperation::UpdateLead, fn (CrmAdapterInterface $adapter) => $adapter->updateLead($externalId, $lead), $lead->toArray());
    }

    public function getLead(string $externalId): CrmOperationResult
    {
        return $this->execute(CrmOperation::GetLead, fn (CrmAdapterInterface $adapter) => $adapter->getLead($externalId), ['external_id' => $externalId]);
    }

    public function createContact(ContactData|array $data): CrmOperationResult
    {
        $contact = $data instanceof ContactData ? $data : ContactData::fromArray($data);

        return $this->execute(CrmOperation::CreateContact, fn (CrmAdapterInterface $adapter) => $adapter->createContact($contact), $contact->toArray());
    }

    public function createTask(TaskData|array $data): CrmOperationResult
    {
        $task = $data instanceof TaskData ? $data : TaskData::fromArray($data);

        return $this->execute(CrmOperation::CreateTask, fn (CrmAdapterInterface $adapter) => $adapter->createTask($task), $task->toArray());
    }

    public function sync(SyncData|array $data): CrmOperationResult
    {
        $sync = $data instanceof SyncData ? $data : SyncData::fromArray($data);

        $result = $this->execute(
            operation: CrmOperation::SyncData,
            callback: fn (CrmAdapterInterface $adapter) => $adapter->sync($sync),
            request: $sync->toArray(),
            isSync: true,
        );

        event(new CrmSyncCompleted(
            connectionId: $this->resolvedConfig()->connectionId,
            status: $result->status(),
            result: $result,
            recordsProcessed: $result->data['TotalCount'] ?? null,
        ));

        return $result;
    }

    public function listPipelines(): CrmOperationResult
    {
        return $this->execute(
            CrmOperation::ListPipelines,
            fn (CrmAdapterInterface $adapter) => $adapter->listPipelines(),
        );
    }

    public function listUsers(): CrmOperationResult
    {
        return $this->execute(
            CrmOperation::ListUsers,
            fn (CrmAdapterInterface $adapter) => $adapter->listUsers(),
        );
    }

    public function syncCallIntelligence(CallIntelligenceSyncData $data): CrmOperationResult
    {
        return $this->execute(
            CrmOperation::SyncCallIntelligence,
            fn (CrmAdapterInterface $adapter) => $adapter->syncCallIntelligence($data),
            ['analysis_id' => $data->analysisId],
        );
    }

    private function execute(
        CrmOperation $operation,
        callable $callback,
        ?array $request = null,
        bool $isSync = false,
    ): CrmOperationResult {
        if ($this->organizationId === null) {
            throw new CrmConnectionNotFoundException('Organization context is required.');
        }

        [$config, $adapter] = $this->resolver->resolve(
            organizationId: $this->organizationId,
            connectionId: $this->connectionId,
        );

        if (! $config->isActive && $operation !== CrmOperation::TestConnection) {
            return CrmOperationResult::failure('CRM connection is inactive.');
        }

        $result = $callback($adapter);

        if ($operation === CrmOperation::TestConnection) {
            event(new CrmConnectionTested($config->connectionId, $result->status(), $result));
        }

        if ($isSync) {
            $this->logs->logSync(
                connectionId: $config->connectionId,
                operation: $operation,
                status: $result->status(),
                payload: ['request' => $request, 'response' => $result->data],
                message: $result->message ?? $result->error,
                recordsProcessed: $result->data['TotalCount'] ?? null,
            );
        } else {
            $this->logs->logConnection(
                connectionId: $config->connectionId,
                operation: $operation,
                status: $result->status(),
                request: $request,
                response: $result->data,
                message: $result->message ?? $result->error,
            );
        }

        return $result;
    }

    private function resolvedConfig(): CrmConnectionConfig
    {
        [$config] = $this->resolver->resolve($this->organizationId, $this->connectionId);

        return $config;
    }
}
