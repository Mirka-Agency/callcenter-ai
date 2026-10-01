<?php

namespace Tests\Unit;

use App\Application\Call\Jobs\BackfillVoipExtensionJob;
use App\Application\Crm\Jobs\SyncCrmDataJob;
use App\Application\Customer\Listeners\SyncCustomerFromAnalysis;
use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Application\Intelligence\Jobs\SyncCrmJob;
use App\Application\Intelligence\Jobs\UpdateEmployeeMetricsJob;
use App\Application\Voip\Jobs\ProcessVoipIngestionJob;
use App\Application\Voip\Jobs\ProcessVoipWebhookJob;
use App\Application\Voip\Jobs\ResolveSimotelCallOutcomeJob;
use App\Application\Voip\Jobs\SyncVoipExtensionsJob;
use App\Jobs\ProvisionDemoOrganizationJob;
use App\Jobs\ProvisionDemoPersonJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueRoutingTest extends TestCase
{
    public function test_analysis_chain_is_pushed_onto_the_analysis_queue(): void
    {
        Queue::fake();

        AnalyzeAudioJob::dispatchChain(5);

        Queue::assertPushedOn('analysis', AnalyzeAudioJob::class);
    }

    public function test_slow_jobs_use_the_followup_queue(): void
    {
        $jobs = [
            new UpdateEmployeeMetricsJob(1),
            new SyncCrmJob(1),
            new SyncCrmDataJob(1),
            new SyncVoipExtensionsJob(1),
            new BackfillVoipExtensionJob(1, '100', 1),
            new ProvisionDemoOrganizationJob(1),
            new ProvisionDemoPersonJob('09120000000', 'Test', 'test@example.com', 'secret'),
        ];

        foreach ($jobs as $job) {
            $this->assertSame('followup', $job->queue);
        }
    }

    public function test_live_voip_jobs_use_the_voip_queue(): void
    {
        $this->assertSame('voip', (new ProcessVoipWebhookJob(1, []))->queue);
        $this->assertSame('voip', (new ProcessVoipIngestionJob(1, []))->queue);
        $this->assertSame('voip', (new ResolveSimotelCallOutcomeJob(1, 1, 'call-1'))->queue);
    }

    public function test_customer_sync_listener_uses_the_followup_queue(): void
    {
        $this->assertSame('followup', app(SyncCustomerFromAnalysis::class)->viaQueue());
    }
}
