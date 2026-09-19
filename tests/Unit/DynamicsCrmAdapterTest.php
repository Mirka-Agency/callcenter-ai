<?php

namespace Tests\Unit;

use App\Domain\Crm\DTOs\ContactData;
use App\Domain\Crm\DTOs\CrmConnectionConfig;
use App\Domain\Crm\DTOs\CrmCredentials;
use App\Domain\Crm\DTOs\CrmSettings;
use App\Domain\Crm\DTOs\LeadData;
use App\Domain\Crm\DTOs\SyncData;
use App\Domain\Crm\DTOs\TaskData;
use App\Domain\Crm\Enums\CrmProviderCode;
use App\Infrastructure\Crm\Adapters\DynamicsCrmAdapter;
use App\Infrastructure\Crm\CrmAdapterRegistry;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DynamicsCrmAdapterTest extends TestCase
{
    public function test_registry_resolves_dynamics_adapter(): void
    {
        $adapter = app(CrmAdapterRegistry::class)->resolve(CrmProviderCode::Dynamics);

        $this->assertInstanceOf(DynamicsCrmAdapter::class, $adapter);
        $this->assertSame(CrmProviderCode::Dynamics, $adapter->getProviderCode());
    }

    public function test_credentials_read_tenant_id(): void
    {
        $credentials = CrmCredentials::fromArray([
            'api_url' => 'https://contoso.crm4.dynamics.com',
            'api_key' => 'client-id',
            'api_token' => 'client-secret',
            'tenant_id' => 'tenant-guid',
        ]);

        $this->assertSame('tenant-guid', $credentials->directoryId());
        $this->assertSame('tenant-guid', $credentials->toArray()['tenant_id']);
    }

    public function test_test_connection_calls_who_am_i(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->dynamicsFake([
            'WhoAmI' => Http::response([
                'UserId' => 'user-1',
                'BusinessUnitId' => 'bu-1',
                'OrganizationId' => 'org-1',
            ]),
        ]));

        $result = $this->adapter()->testConnection();

        $this->assertTrue($result->success);
        $this->assertSame('user-1', $result->externalId);
        $this->assertSame('اتصال به Microsoft Dynamics 365 برقرار شد.', $result->message);
    }

    public function test_test_connection_fails_when_credentials_are_incomplete(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $adapter = new DynamicsCrmAdapter;
        $adapter->configure(new CrmConnectionConfig(
            connectionId: 1,
            organizationId: 1,
            providerCode: CrmProviderCode::Dynamics,
            name: 'Dynamics',
            credentials: new CrmCredentials(apiUrl: 'https://contoso.crm4.dynamics.com'),
            settings: new CrmSettings,
        ));

        $result = $adapter->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('incomplete', (string) $result->error);
    }

    public function test_create_lead_creates_contact_and_opportunity(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->dynamicsFake());

        $result = $this->adapter()->createLead(new LeadData(
            title: '[لید بالا] علی رضایی',
            firstName: 'علی',
            lastName: 'رضایی',
            phone: '09120000000',
            company: 'شرکت نمونه',
            description: 'خلاصه تماس',
            source: 'call_intelligence',
            pipelineStageId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            ownerId: '99999999-8888-7777-6666-555555555555',
        ));

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $result->externalId);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/contacts')
            && $request['lastname'] === 'رضایی'
            && $request['mobilephone'] === '09120000000');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/opportunities')
            && $request['name'] === '[لید بالا] علی رضایی'
            && $request['parentcontactid@odata.bind'] === '/contacts(11111111-2222-3333-4444-555555555555)'
            && $request['ownerid@odata.bind'] === '/systemusers(99999999-8888-7777-6666-555555555555)');
    }

    public function test_create_contact_reuses_existing_record(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->dynamicsFake([
            'contacts_get' => Http::response([
                'value' => [['contactid' => '11111111-2222-3333-4444-555555555555']],
            ]),
        ]));

        $result = $this->adapter()->createContact(new ContactData(
            firstName: 'علی',
            lastName: 'رضایی',
            phone: '09120000000',
        ));

        $this->assertTrue($result->success);
        $this->assertSame('11111111-2222-3333-4444-555555555555', $result->externalId);
        $this->assertTrue($result->data['existing'] ?? false);

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/contacts'));
    }

    public function test_create_task_binds_opportunity(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->dynamicsFake());

        $result = $this->adapter()->createTask(new TaskData(
            title: 'پیگیری مشتری',
            description: 'تماس مجدد',
            dueAt: '2026-09-20T12:00:00+03:30',
            relatedExternalId: 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ));

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('task-1', $result->externalId);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/tasks')
            && $request['subject'] === 'پیگیری مشتری'
            && $request['regardingobjectid_opportunity@odata.bind'] === '/opportunities(aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee)');
    }

    public function test_list_pipelines_groups_business_process_stages(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->dynamicsFake());

        $result = $this->adapter()->listPipelines();

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $result->data['pipelines'][0]['id']);
        $this->assertSame('Opportunity Sales Process', $result->data['pipelines'][0]['title']);
        $this->assertSame('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', $result->data['pipelines'][0]['stages'][0]['id']);
    }

    public function test_list_users_maps_system_users(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->dynamicsFake());

        $result = $this->adapter()->listUsers();

        $this->assertTrue($result->success, (string) $result->error);
        $this->assertSame('99999999-8888-7777-6666-555555555555', $result->data['users'][0]['id']);
        $this->assertSame('Sara Sale', $result->data['users'][0]['name']);
        $this->assertSame('sara@contoso.com', $result->data['users'][0]['email']);
    }

    public function test_sync_reads_contacts(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->dynamicsFake([
            'contacts_get' => Http::response([
                'value' => [
                    ['contactid' => 'c1', 'fullname' => 'Ali'],
                    ['contactid' => 'c2', 'fullname' => 'Sara'],
                ],
            ]),
        ]));

        $result = $this->adapter()->sync(new SyncData(entity: 'contacts'));

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->data['TotalCount']);
    }

    /**
     * @param  array<string, PromiseInterface|Response>  $overrides
     * @return \Closure(Request): Response
     */
    private function dynamicsFake(array $overrides = []): \Closure
    {
        return function (Request $request) use ($overrides) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, 'login.microsoftonline.com')) {
                return $overrides['token'] ?? Http::response([
                    'access_token' => 'test-token',
                    'expires_in' => 3600,
                ]);
            }

            if (str_contains($url, 'WhoAmI')) {
                return $overrides['WhoAmI'] ?? Http::response(['UserId' => 'user-1']);
            }

            if (str_contains($url, '/contacts') && $method === 'GET') {
                return $overrides['contacts_get'] ?? Http::response(['value' => []]);
            }

            if (str_contains($url, '/contacts') && $method === 'POST') {
                return $overrides['contacts_post'] ?? Http::response([
                    'contactid' => '11111111-2222-3333-4444-555555555555',
                ], 201);
            }

            if (str_contains($url, '/opportunities') && $method === 'POST') {
                return $overrides['opportunities_post'] ?? Http::response([
                    'opportunityid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                ], 201);
            }

            if (str_contains($url, '/tasks') && $method === 'POST') {
                return $overrides['tasks_post'] ?? Http::response([
                    'activityid' => 'task-1',
                ], 201);
            }

            if (str_contains($url, '/workflows')) {
                return $overrides['workflows'] ?? Http::response([
                    'value' => [[
                        'workflowid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                        'name' => 'Opportunity Sales Process',
                        'primaryentity' => 'opportunity',
                    ]],
                ]);
            }

            if (str_contains($url, '/processstages')) {
                return $overrides['processstages'] ?? Http::response([
                    'value' => [[
                        'processstageid' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                        'stagename' => 'Qualify',
                        '_processid_value' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                    ]],
                ]);
            }

            if (str_contains($url, '/systemusers')) {
                return $overrides['systemusers'] ?? Http::response([
                    'value' => [[
                        'systemuserid' => '99999999-8888-7777-6666-555555555555',
                        'fullname' => 'Sara Sale',
                        'internalemailaddress' => 'sara@contoso.com',
                    ]],
                ]);
            }

            return Http::response(['error' => ['message' => 'Unmocked '.$method.' '.$url]], 500);
        };
    }

    private function adapter(): DynamicsCrmAdapter
    {
        $adapter = new DynamicsCrmAdapter;
        $adapter->configure(new CrmConnectionConfig(
            connectionId: 1,
            organizationId: 1,
            providerCode: CrmProviderCode::Dynamics,
            name: 'Dynamics',
            credentials: new CrmCredentials(
                apiUrl: 'https://contoso.crm4.dynamics.com',
                apiKey: 'client-id',
                apiToken: 'client-secret',
                tenantId: 'tenant-guid',
            ),
            settings: new CrmSettings(
                pipelineId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                pipelineStageId: 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                dealOwnerId: '99999999-8888-7777-6666-555555555555',
            ),
        ));

        return $adapter;
    }
}
