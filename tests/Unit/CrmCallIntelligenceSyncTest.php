<?php

namespace Tests\Unit;

use App\Domain\Crm\DTOs\CallIntelligenceSyncData;
use App\Domain\Crm\DTOs\ContactData;
use App\Domain\Crm\DTOs\CrmConnectionConfig;
use App\Domain\Crm\DTOs\CrmCredentials;
use App\Domain\Crm\DTOs\CrmSettings;
use App\Domain\Crm\DTOs\LeadData;
use App\Domain\Crm\DTOs\SyncData;
use App\Domain\Crm\DTOs\TaskData;
use App\Domain\Crm\Enums\CrmProviderCode;
use App\Domain\Crm\ValueObjects\CrmOperationResult;
use App\Infrastructure\Crm\Adapters\AbstractCrmAdapter;
use PHPUnit\Framework\TestCase;

class CrmCallIntelligenceSyncTest extends TestCase
{
    public function test_sync_prioritizes_high_lead_score_with_shorter_due_date(): void
    {
        $adapter = $this->makeAdapter();

        $data = new CallIntelligenceSyncData(
            organizationId: 1,
            connectionId: 1,
            analysisId: 10,
            callId: 20,
            organizationUserId: 3,
            summary: 'خلاصه تماس',
            score: 85,
            sentiment: 'positive',
            strengths: [],
            weaknesses: [],
            nextActions: ['تماس مجدد با مشتری'],
            customerPhone: '09120000000',
            leadQuality: [
                'score' => 82,
                'level' => 'high',
                'reason' => 'تمایل بالا به خرید',
                'buying_intent_signals' => ['پرسش قیمت'],
            ],
            concerns: [
                ['type' => 'price', 'text' => 'نگرانی از قیمت', 'severity' => 'medium'],
            ],
        );

        $result = $adapter->syncCallIntelligence($data);

        $this->assertTrue($result->success);
        $this->assertSame(82, $result->data['lead_score']);
        $this->assertSame('high', $result->data['lead_level']);
        $this->assertCount(1, $adapter->tasks);
        $this->assertStringContainsString('[لید بالا]', $adapter->tasks[0]->title);
        $this->assertStringContainsString('نگرانی از قیمت', $adapter->tasks[0]->description);
        $this->assertSame(82, $adapter->tasks[0]->metadata['sales_priority']);
        $this->assertNotNull($adapter->tasks[0]->dueAt);
        $this->assertSame([], $adapter->leads);
    }

    public function test_sync_creates_deal_when_pipeline_stage_is_configured(): void
    {
        $adapter = $this->makeAdapter();
        $adapter->configure(new CrmConnectionConfig(
            connectionId: 1,
            organizationId: 1,
            providerCode: CrmProviderCode::Didar,
            name: 'Didar',
            credentials: new CrmCredentials(apiUrl: 'https://app.didar.me/api', apiKey: 'test'),
            settings: new CrmSettings(
                pipelineId: 'pipeline-1',
                pipelineStageId: 'stage-1',
                dealOwnerId: 'owner-1',
            ),
        ));

        $data = new CallIntelligenceSyncData(
            organizationId: 1,
            connectionId: 1,
            analysisId: 11,
            callId: 21,
            organizationUserId: 3,
            summary: 'خلاصه تماس',
            score: 70,
            sentiment: 'neutral',
            strengths: [],
            weaknesses: [],
            nextActions: ['پیگیری فردا'],
            customerPhone: '09121111111',
            customerIdentity: [
                'person_name' => 'علی رضایی',
                'company_name' => 'شرکت نمونه',
            ],
            leadQuality: [
                'score' => 70,
                'level' => 'high',
                'reason' => 'علاقه به خرید',
            ],
        );

        $result = $adapter->syncCallIntelligence($data);

        $this->assertTrue($result->success);
        $this->assertCount(1, $adapter->leads);
        $this->assertSame('stage-1', $adapter->leads[0]->pipelineStageId);
        $this->assertSame('owner-1', $adapter->leads[0]->ownerId);
        $this->assertStringContainsString('علی رضایی', $adapter->leads[0]->title);
        $this->assertCount(1, $adapter->tasks);
        $this->assertSame('deal-external-1', $adapter->tasks[0]->relatedExternalId);
        $this->assertSame('deal-external-1', $result->externalId);
        $this->assertContains('deal_created', $result->data['actions']);
    }

    /**
     * @return AbstractCrmAdapter&object{tasks: list<TaskData>, leads: list<LeadData>}
     */
    private function makeAdapter(): AbstractCrmAdapter
    {
        $adapter = new class extends AbstractCrmAdapter
        {
            /** @var list<TaskData> */
            public array $tasks = [];

            /** @var list<LeadData> */
            public array $leads = [];

            public function getProviderCode(): CrmProviderCode
            {
                return CrmProviderCode::Didar;
            }

            public function testConnection(): CrmOperationResult
            {
                return CrmOperationResult::success();
            }

            public function createLead(LeadData $lead): CrmOperationResult
            {
                $this->leads[] = $lead;

                return CrmOperationResult::success(externalId: 'deal-external-1');
            }

            public function updateLead(string $externalId, LeadData $lead): CrmOperationResult
            {
                return CrmOperationResult::success();
            }

            public function getLead(string $externalId): CrmOperationResult
            {
                return CrmOperationResult::success();
            }

            public function createContact(ContactData $contact): CrmOperationResult
            {
                return CrmOperationResult::success();
            }

            public function createTask(TaskData $task): CrmOperationResult
            {
                $this->tasks[] = $task;

                return CrmOperationResult::success();
            }

            public function sync(SyncData $sync): CrmOperationResult
            {
                return CrmOperationResult::success();
            }

            public function listPipelines(): CrmOperationResult
            {
                return CrmOperationResult::success(data: ['pipelines' => []]);
            }

            public function listUsers(): CrmOperationResult
            {
                return CrmOperationResult::success(data: ['users' => []]);
            }
        };

        $adapter->configure(new CrmConnectionConfig(
            connectionId: 1,
            organizationId: 1,
            providerCode: CrmProviderCode::Didar,
            name: 'Didar',
            credentials: new CrmCredentials(apiUrl: 'https://app.didar.me/api', apiKey: 'test'),
            settings: new CrmSettings,
        ));

        return $adapter;
    }
}
