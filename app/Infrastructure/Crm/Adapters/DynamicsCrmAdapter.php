<?php

namespace App\Infrastructure\Crm\Adapters;

use App\Contracts\ProvidesEmployeeIntegrationMeta;
use App\Domain\Crm\DTOs\ContactData;
use App\Domain\Crm\DTOs\CrmPipelineOption;
use App\Domain\Crm\DTOs\CrmPipelineStageOption;
use App\Domain\Crm\DTOs\CrmUserOption;
use App\Domain\Crm\DTOs\LeadData;
use App\Domain\Crm\DTOs\SyncData;
use App\Domain\Crm\DTOs\TaskData;
use App\Domain\Crm\Enums\CrmProviderCode;
use App\Domain\Crm\ValueObjects\CrmOperationResult;
use App\Infrastructure\Crm\Clients\DynamicsDataverseClient;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class DynamicsCrmAdapter extends AbstractCrmAdapter implements ProvidesEmployeeIntegrationMeta
{
    private ?DynamicsDataverseClient $client = null;

    public static function employeeIntegrationMetaDefinitions(): array
    {
        return [
            [
                'key' => 'crm_user_id',
                'name' => 'شناسه کاربر Dynamics (systemuserid)',
                'field_type' => 'text',
                'is_required' => true,
                'sort_order' => 1,
                'help_text' => 'GUID کاربر سیستم در Dataverse. از لیست کاربران اتصال CRM قابل کپی است.',
            ],
            [
                'key' => 'mobile',
                'name' => 'شماره موبایل',
                'field_type' => 'tel',
                'is_required' => false,
                'sort_order' => 2,
            ],
            [
                'key' => 'email',
                'name' => 'ایمیل',
                'field_type' => 'email',
                'is_required' => false,
                'sort_order' => 3,
            ],
        ];
    }

    public function getProviderCode(): CrmProviderCode
    {
        return CrmProviderCode::Dynamics;
    }

    public function testConnection(): CrmOperationResult
    {
        try {
            $response = $this->client()->get('WhoAmI');
        } catch (Throwable $exception) {
            return $this->parseHttpFailure($exception->getMessage());
        }

        if ($response->failed()) {
            return $this->parseHttpFailure($this->client()->errorMessage($response), $response->json());
        }

        return CrmOperationResult::success(
            externalId: $response->json('UserId'),
            data: $response->json() ?? [],
            message: 'اتصال به Microsoft Dynamics 365 برقرار شد.',
        );
    }

    public function createLead(LeadData $lead): CrmOperationResult
    {
        $lead = $lead->withDealDefaults(
            pipelineStageId: $this->config->settings->pipelineStageId,
            ownerId: $this->config->settings->dealOwnerId,
        );

        $contactId = $this->findOrCreateContactId($lead);
        $payload = $this->mapOpportunityPayload($lead, $contactId);

        try {
            $response = $this->postOpportunity($payload);
        } catch (Throwable $exception) {
            return $this->parseHttpFailure($exception->getMessage());
        }

        if ($response->failed()) {
            return $this->parseHttpFailure($this->client()->errorMessage($response), $response->json());
        }

        $opportunityId = $this->client()->extractRecordId($response, 'opportunityid');

        return CrmOperationResult::success(
            externalId: $opportunityId,
            data: $response->json() ?? ['opportunityid' => $opportunityId, 'contactid' => $contactId],
            message: 'فرصت فروش در Dynamics 365 ایجاد شد.',
        );
    }

    public function updateLead(string $externalId, LeadData $lead): CrmOperationResult
    {
        $id = DynamicsDataverseClient::unbrace($externalId);
        $payload = $this->mapOpportunityPayload($lead, $lead->contactId);

        try {
            $response = $this->client()->patch($this->entityPath('opportunities', $id), $payload);
        } catch (Throwable $exception) {
            return $this->parseHttpFailure($exception->getMessage());
        }

        if ($response->failed()) {
            return $this->parseHttpFailure($this->client()->errorMessage($response), $response->json());
        }

        return CrmOperationResult::success(
            externalId: $id,
            data: $response->json() ?? ['opportunityid' => $id],
            message: 'فرصت فروش در Dynamics 365 به‌روزرسانی شد.',
        );
    }

    public function getLead(string $externalId): CrmOperationResult
    {
        $id = DynamicsDataverseClient::unbrace($externalId);

        try {
            $response = $this->client()->get($this->entityPath('opportunities', $id));
        } catch (Throwable $exception) {
            return $this->parseHttpFailure($exception->getMessage());
        }

        if ($response->status() === 404) {
            try {
                $response = $this->client()->get($this->entityPath('leads', $id));
            } catch (Throwable $exception) {
                return $this->parseHttpFailure($exception->getMessage());
            }
        }

        if ($response->failed()) {
            return $this->parseHttpFailure($this->client()->errorMessage($response), $response->json());
        }

        $body = $response->json() ?? [];

        return CrmOperationResult::success(
            externalId: (string) ($body['opportunityid'] ?? $body['leadid'] ?? $id),
            data: $body,
            message: 'رکورد از Dynamics 365 خوانده شد.',
        );
    }

    public function createContact(ContactData $contact): CrmOperationResult
    {
        try {
            $existingId = $this->findContactId($contact->phone, $contact->email);
            if ($existingId !== null) {
                return CrmOperationResult::success(
                    externalId: $existingId,
                    data: ['contactid' => $existingId, 'existing' => true],
                    message: 'مخاطب از قبل در Dynamics 365 وجود داشت.',
                );
            }

            $response = $this->client()->post('contacts', $this->mapContactPayload($contact));
        } catch (Throwable $exception) {
            return $this->parseHttpFailure($exception->getMessage());
        }

        if ($response->failed()) {
            return $this->parseHttpFailure($this->client()->errorMessage($response), $response->json());
        }

        $contactId = $this->client()->extractRecordId($response, 'contactid');

        return CrmOperationResult::success(
            externalId: $contactId,
            data: $response->json() ?? ['contactid' => $contactId],
            message: 'مخاطب در Dynamics 365 ایجاد شد.',
        );
    }

    public function createTask(TaskData $task): CrmOperationResult
    {
        $payload = array_filter([
            'subject' => $task->title,
            'description' => $task->description,
            'scheduledend' => $this->toDataverseDate($task->dueAt),
        ], fn ($value) => $value !== null && $value !== '');

        if (filled($task->relatedExternalId) && DynamicsDataverseClient::isGuid((string) $task->relatedExternalId)) {
            $payload['regardingobjectid_opportunity@odata.bind'] = $this->bind('opportunities', (string) $task->relatedExternalId);
        }

        if (filled($task->assignee) && DynamicsDataverseClient::isGuid($task->assignee)) {
            $payload['ownerid@odata.bind'] = $this->bind('systemusers', $task->assignee);
        } elseif (filled($this->config->settings->dealOwnerId) && DynamicsDataverseClient::isGuid($this->config->settings->dealOwnerId)) {
            $payload['ownerid@odata.bind'] = $this->bind('systemusers', $this->config->settings->dealOwnerId);
        }

        try {
            $response = $this->client()->post('tasks', $payload);
        } catch (Throwable $exception) {
            return $this->parseHttpFailure($exception->getMessage());
        }

        if ($response->failed()) {
            return $this->parseHttpFailure($this->client()->errorMessage($response), $response->json());
        }

        $taskId = $this->client()->extractRecordId($response, 'activityid');

        return CrmOperationResult::success(
            externalId: $taskId,
            data: $response->json() ?? ['activityid' => $taskId],
            message: 'وظیفه در Dynamics 365 ایجاد شد.',
        );
    }

    public function sync(SyncData $sync): CrmOperationResult
    {
        $entity = match ($sync->entity) {
            'leads', 'deals', 'opportunities' => 'opportunities',
            default => 'contacts',
        };

        $query = [
            '$top' => 100,
            '$orderby' => 'modifiedon desc',
        ];

        if ($entity === 'contacts') {
            $query['$select'] = 'contactid,fullname,firstname,lastname,emailaddress1,mobilephone,telephone1,modifiedon';
        } else {
            $query['$select'] = 'opportunityid,name,description,modifiedon,statecode';
        }

        try {
            $response = $this->client()->get($entity, $query);
        } catch (Throwable $exception) {
            return $this->parseHttpFailure($exception->getMessage());
        }

        if ($response->failed()) {
            return $this->parseHttpFailure($this->client()->errorMessage($response), $response->json());
        }

        $records = $response->json('value') ?? [];
        $count = is_array($records) ? count($records) : 0;

        return CrmOperationResult::success(
            data: [
                'value' => $records,
                'TotalCount' => $count,
            ],
            message: "{$count} رکورد از Dynamics 365 همگام شد.",
        );
    }

    public function listPipelines(): CrmOperationResult
    {
        try {
            $processes = $this->client()->get('workflows', [
                '$select' => 'workflowid,name,uniquename,primaryentity',
                '$filter' => "category eq 4 and statecode eq 1 and (primaryentity eq 'opportunity' or primaryentity eq 'lead')",
                '$orderby' => 'name asc',
            ]);
            $stages = $this->client()->get('processstages', [
                '$select' => 'processstageid,stagename,_processid_value',
            ]);
        } catch (Throwable $exception) {
            return $this->parseHttpFailure($exception->getMessage());
        }

        if ($processes->failed()) {
            return $this->parseHttpFailure($this->client()->errorMessage($processes), $processes->json());
        }

        if ($stages->failed()) {
            return $this->parseHttpFailure($this->client()->errorMessage($stages), $stages->json());
        }

        $stagesByProcess = [];
        foreach ($stages->json('value') ?? [] as $index => $stage) {
            if (! is_array($stage)) {
                continue;
            }

            $processId = DynamicsDataverseClient::unbrace((string) ($stage['_processid_value'] ?? $stage['processid'] ?? ''));
            if ($processId === '') {
                continue;
            }

            $stagesByProcess[$processId][] = [
                'id' => DynamicsDataverseClient::unbrace((string) ($stage['processstageid'] ?? '')),
                'title' => (string) ($stage['stagename'] ?? 'مرحله'),
                'index' => (int) $index,
            ];
        }

        $pipelines = [];
        foreach ($processes->json('value') ?? [] as $process) {
            if (! is_array($process)) {
                continue;
            }

            $id = DynamicsDataverseClient::unbrace((string) ($process['workflowid'] ?? ''));
            if ($id === '') {
                continue;
            }

            $pipelines[] = (new CrmPipelineOption(
                id: $id,
                title: (string) ($process['name'] ?? 'فرآیند فروش'),
                stages: array_map(
                    fn (array $stage) => CrmPipelineStageOption::fromArray($stage),
                    $stagesByProcess[$id] ?? [],
                ),
            ))->toArray();
        }

        if ($pipelines === []) {
            $pipelines[] = (new CrmPipelineOption(
                id: 'opportunity',
                title: 'فرصت فروش',
                stages: [
                    CrmPipelineStageOption::fromArray([
                        'id' => 'qualify',
                        'title' => 'ایجاد فرصت',
                        'index' => 0,
                    ]),
                ],
            ))->toArray();
        }

        return CrmOperationResult::success(
            data: ['pipelines' => $pipelines],
            message: 'کاریزهای Dynamics 365 بارگذاری شد.',
        );
    }

    public function listUsers(): CrmOperationResult
    {
        try {
            $response = $this->client()->get('systemusers', [
                '$select' => 'systemuserid,fullname,internalemailaddress,firstname,lastname',
                '$filter' => 'isdisabled eq false and accessmode eq 0',
                '$orderby' => 'fullname asc',
                '$top' => 200,
            ]);
        } catch (Throwable $exception) {
            return $this->parseHttpFailure($exception->getMessage());
        }

        if ($response->failed()) {
            return $this->parseHttpFailure($this->client()->errorMessage($response), $response->json());
        }

        $users = [];
        foreach ($response->json('value') ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $user = CrmUserOption::fromArray([
                'id' => $row['systemuserid'] ?? '',
                'name' => $row['fullname'] ?? '',
                'email' => $row['internalemailaddress'] ?? null,
            ]);

            if ($user->id === '') {
                continue;
            }

            $users[] = $user->toArray();
        }

        return CrmOperationResult::success(
            data: ['users' => $users],
            message: 'کاربران Dynamics 365 بارگذاری شد.',
        );
    }

    private function client(): DynamicsDataverseClient
    {
        return $this->client ??= new DynamicsDataverseClient(
            credentials: $this->config->credentials,
            settings: $this->config->settings,
        );
    }

    private function postOpportunity(array $payload): Response
    {
        $response = $this->client()->post('opportunities', $payload);

        if ($response->successful()) {
            return $response;
        }

        $withoutProcess = $payload;
        unset(
            $withoutProcess['processid@odata.bind'],
            $withoutProcess['stageid@odata.bind'],
        );

        if ($withoutProcess === $payload) {
            return $response;
        }

        return $this->client()->post('opportunities', $withoutProcess);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapOpportunityPayload(LeadData $lead, ?string $contactId): array
    {
        $payload = array_filter([
            'name' => $lead->title !== '' ? $lead->title : trim(($lead->firstName ?? '').' '.($lead->lastName ?? '')),
            'description' => $this->opportunityDescription($lead),
        ], fn ($value) => $value !== null && $value !== '');

        if ($contactId && DynamicsDataverseClient::isGuid($contactId)) {
            $payload['parentcontactid@odata.bind'] = $this->bind('contacts', $contactId);
        }

        if (filled($lead->ownerId) && DynamicsDataverseClient::isGuid($lead->ownerId)) {
            $payload['ownerid@odata.bind'] = $this->bind('systemusers', $lead->ownerId);
        }

        $pipelineId = $this->config->settings->pipelineId;
        if (filled($pipelineId) && DynamicsDataverseClient::isGuid($pipelineId)) {
            $payload['processid@odata.bind'] = $this->bind('workflows', $pipelineId);
        }

        if (filled($lead->pipelineStageId) && DynamicsDataverseClient::isGuid($lead->pipelineStageId)) {
            $payload['stageid@odata.bind'] = $this->bind('processstages', $lead->pipelineStageId);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapContactPayload(ContactData $contact): array
    {
        $lastName = trim((string) $contact->lastName);
        $firstName = trim((string) $contact->firstName);

        if ($lastName === '' && $firstName !== '') {
            $lastName = $firstName;
            $firstName = '';
        }

        if ($lastName === '') {
            $lastName = filled($contact->phone) ? (string) $contact->phone : 'مخاطب تماس';
        }

        return array_filter([
            'firstname' => $firstName !== '' ? $firstName : null,
            'lastname' => $lastName,
            'emailaddress1' => $contact->email,
            'mobilephone' => $contact->phone,
            'telephone1' => $contact->phone,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function findOrCreateContactId(LeadData $lead): ?string
    {
        $existing = $this->findContactId($lead->phone, $lead->email);
        if ($existing !== null) {
            return $existing;
        }

        $hasIdentity = filled($lead->firstName) || filled($lead->lastName) || filled($lead->phone) || filled($lead->email);
        if (! $hasIdentity) {
            return null;
        }

        $result = $this->createContact(new ContactData(
            firstName: $lead->firstName,
            lastName: $lead->lastName,
            email: $lead->email,
            phone: $lead->phone,
            company: $lead->company,
        ));

        return $result->success ? $result->externalId : null;
    }

    private function findContactId(?string $phone, ?string $email): ?string
    {
        $clauses = [];

        if (filled($phone)) {
            $escaped = DynamicsDataverseClient::odataString($phone);
            $clauses[] = "mobilephone eq '{$escaped}'";
            $clauses[] = "telephone1 eq '{$escaped}'";
        }

        if (filled($email)) {
            $escaped = DynamicsDataverseClient::odataString($email);
            $clauses[] = "emailaddress1 eq '{$escaped}'";
        }

        if ($clauses === []) {
            return null;
        }

        try {
            $response = $this->client()->get('contacts', [
                '$select' => 'contactid',
                '$filter' => implode(' or ', $clauses),
                '$top' => 1,
            ]);
        } catch (RuntimeException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $id = $response->json('value.0.contactid');

        return is_string($id) && $id !== '' ? DynamicsDataverseClient::unbrace($id) : null;
    }

    private function opportunityDescription(LeadData $lead): ?string
    {
        $parts = array_filter([
            $lead->description,
            filled($lead->company) ? 'شرکت: '.$lead->company : null,
            filled($lead->phone) ? 'تلفن: '.$lead->phone : null,
            filled($lead->email) ? 'ایمیل: '.$lead->email : null,
            filled($lead->source) ? 'منبع: '.$lead->source : null,
        ]);

        return $parts === [] ? null : implode("\n", $parts);
    }

    private function bind(string $entitySet, string $id): string
    {
        return sprintf('/%s(%s)', $entitySet, DynamicsDataverseClient::unbrace($id));
    }

    private function entityPath(string $entitySet, string $id): string
    {
        return sprintf('%s(%s)', $entitySet, DynamicsDataverseClient::unbrace($id));
    }

    private function toDataverseDate(?string $dueAt): ?string
    {
        if (! filled($dueAt)) {
            return null;
        }

        $timestamp = strtotime($dueAt);

        return $timestamp === false ? $dueAt : gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }
}
