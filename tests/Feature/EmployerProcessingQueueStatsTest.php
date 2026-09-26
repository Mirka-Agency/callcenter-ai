<?php

namespace Tests\Feature;

use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Processing\Enums\ProcessingJobStage;
use App\Domain\Processing\Enums\ProcessingJobStatus;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Enums\UserRole;
use App\Infrastructure\Voip\Adapters\NullVoipAdapter;
use App\Livewire\Employer\ProcessingQueue\Index;
use App\Models\Call;
use App\Models\CallProcessingJob;
use App\Models\EmployeeIntegrationMeta;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\OrganizationVoipConnection;
use App\Models\User;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class EmployerProcessingQueueStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_cards_count_only_calls_on_defined_extensions(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $agentUser = User::factory()->create();
        $agent = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $agentUser->id,
            'first_name' => 'Ali',
            'last_name' => 'One',
            'is_active' => true,
        ]);
        $connection = $this->voipConnection($organization);

        EmployeeIntegrationMeta::query()->create([
            'organization_user_id' => $agent->id,
            'integratable_type' => OrganizationVoipConnection::class,
            'integratable_id' => $connection->id,
            'key' => 'extension',
            'value' => '111',
        ]);

        $analyzedWithoutJob = $this->voipCall($organization, $agent, $connection, '111', 'analyzed-without-job', CallProcessingStatus::Analyzed);
        $analyzedWithJob = $this->voipCall($organization, $agent, $connection, '111', 'analyzed-with-job', CallProcessingStatus::Analyzed);
        $requeued = $this->voipCall($organization, $agent, $connection, '111', 'requeued', CallProcessingStatus::Analyzed);
        $analysisFailed = $this->voipCall($organization, $agent, $connection, '111', 'analysis-failed', CallProcessingStatus::Failed);
        $undefined = $this->voipCall($organization, $agent, $connection, '112', 'undefined-112', CallProcessingStatus::Analyzed);
        $manual = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'source' => ConversationSource::ManualUpload,
            'provider_code' => 'manual',
            'external_call_id' => 'manual-1',
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'status' => CallStatus::Completed->value,
            'processing_status' => CallProcessingStatus::Pending,
            'duration_seconds' => 30,
            'started_at' => now(),
        ]);

        $this->job($analyzedWithJob, ProcessingJobStatus::Completed);
        $this->job($requeued, ProcessingJobStatus::Completed);
        $this->job($requeued, ProcessingJobStatus::Queued);
        $this->job($analysisFailed, ProcessingJobStatus::Failed);
        $this->job($undefined, ProcessingJobStatus::Completed);
        $this->job($manual, ProcessingJobStatus::Processing);
        $uploading = $this->voipCall($organization, $agent, $connection, '111', 'defined-uploading', CallProcessingStatus::Downloading);
        $cancelled = $this->voipCall($organization, $agent, $connection, '111', 'defined-cancelled', CallProcessingStatus::Failed);
        $this->job($uploading, ProcessingJobStatus::Uploading);
        $this->job($cancelled, ProcessingJobStatus::Cancelled);

        $component = Livewire::actingAs($employer)->test(Index::class);

        $stats = $component->viewData('stats');

        $this->assertNotNull($analyzedWithoutJob->id);
        $this->assertSame(1, $stats['queued']);
        $this->assertSame(2, $stats['processing']);
        $this->assertSame(2, $stats['completed']);
        $this->assertSame(2, $stats['failed']);
        $this->assertSame(7, $stats['total']);
        $component->assertDontSee('undefined-112');
    }

    private function job(Call $call, ProcessingJobStatus $status): CallProcessingJob
    {
        return CallProcessingJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'call_id' => $call->id,
            'organization_id' => $call->organization_id,
            'organization_user_id' => $call->organization_user_id,
            'file_name' => $call->external_call_id.'.wav',
            'status' => $status,
            'stage' => ProcessingJobStage::Queued,
            'progress_percentage' => 0,
        ]);
    }

    private function voipConnection(Organization $organization): OrganizationVoipConnection
    {
        $provider = VoipProvider::query()->create([
            'name' => 'Custom',
            'code' => VoipProviderCode::Custom->value.'-'.$organization->id,
            'adapter_class' => NullVoipAdapter::class,
            'is_active' => true,
        ]);

        return OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $provider->id,
            'name' => 'Asterisk',
            'credentials' => [],
            'settings' => [],
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    private function voipCall(
        Organization $organization,
        OrganizationUser $agent,
        OrganizationVoipConnection $connection,
        string $extension,
        string $externalCallId,
        CallProcessingStatus $processingStatus = CallProcessingStatus::Pending,
    ): Call {
        $log = VoipCallLog::query()->create([
            'organization_id' => $organization->id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => $externalCallId,
            'direction' => 'inbound',
            'source_number' => '09120000001',
            'destination_number' => $extension,
            'status' => CallStatus::Completed->value,
            'started_at' => now(),
            'duration' => 40,
            'raw_payload' => [],
        ]);

        return Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $agent->id,
            'organization_voip_connection_id' => $connection->id,
            'voip_call_log_id' => $log->id,
            'source' => ConversationSource::Voip,
            'provider_code' => VoipProviderCode::Custom->value,
            'external_call_id' => $externalCallId,
            'direction' => 'inbound',
            'caller_number' => '09120000001',
            'receiver_number' => $extension,
            'status' => CallStatus::Completed->value,
            'processing_status' => $processingStatus,
            'duration_seconds' => 40,
            'started_at' => now(),
        ]);
    }
}
