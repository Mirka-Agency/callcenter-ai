<?php

namespace Tests\Feature;

use App\Domain\Voip\Enums\CallDirection;
use App\Domain\Voip\Enums\CallStatus;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Filament\Resources\OrganizationVoipConnections\Pages\EditOrganizationVoipConnection;
use App\Filament\Resources\OrganizationVoipConnections\RelationManagers\CallLogsRelationManager;
use App\Filament\Resources\OrganizationVoipConnections\RelationManagers\WebhookLogsRelationManager;
use App\Infrastructure\Voip\Adapters\NullVoipAdapter;
use App\Models\Organization;
use App\Models\OrganizationVoipConnection;
use App\Models\User;
use App\Models\VoipCallLog;
use App\Models\VoipProvider;
use App\Models\VoipWebhookLog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VoipReportAdminFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_log_relation_manager_filters_by_call_id_extension_and_direction(): void
    {
        $this->actingAsAdmin();
        $connection = $this->createConnection();

        $matching = $this->createCallLog($connection, [
            'external_call_id' => 'keep-call',
            'direction' => CallDirection::Inbound,
            'source_number' => '09120000000',
            'destination_number' => '101',
            'raw_payload' => ['resolved_extension' => '101'],
        ]);
        $other = $this->createCallLog($connection, [
            'external_call_id' => 'drop-call',
            'direction' => CallDirection::Outbound,
            'source_number' => '202',
            'destination_number' => '09121111111',
            'raw_payload' => ['extension' => '202'],
        ]);

        Livewire::test(CallLogsRelationManager::class, [
            'ownerRecord' => $connection,
            'pageClass' => EditOrganizationVoipConnection::class,
        ])
            ->assertCanSeeTableRecords([$matching, $other])
            ->filterTable('call_id', ['value' => 'keep-call'])
            ->filterTable('extension', ['value' => '101'])
            ->filterTable('direction', CallDirection::Inbound->value)
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_webhook_log_relation_manager_filters_by_call_id_extension_and_direction(): void
    {
        $this->actingAsAdmin();
        $connection = $this->createConnection();

        $matching = $this->createWebhookLog($connection, [
            'resolved_extension' => '101',
            'payload' => [
                'cuid' => 'hook-keep',
                'direction' => 'inbound',
                'extension' => '101',
            ],
        ]);
        $other = $this->createWebhookLog($connection, [
            'resolved_extension' => '202',
            'payload' => [
                'call_id' => 'hook-drop',
                'direction' => 'outbound',
                'extension' => '202',
            ],
        ]);

        Livewire::test(WebhookLogsRelationManager::class, [
            'ownerRecord' => $connection,
            'pageClass' => EditOrganizationVoipConnection::class,
        ])
            ->assertCanSeeTableRecords([$matching, $other])
            ->filterTable('call_id', ['value' => 'hook-keep'])
            ->filterTable('extension', ['value' => '101'])
            ->filterTable('direction', CallDirection::Inbound->value)
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }

    private function actingAsAdmin(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
    }

    private function createConnection(): OrganizationVoipConnection
    {
        $organization = Organization::factory()->create();
        $provider = VoipProvider::query()->create([
            'name' => 'Test',
            'code' => VoipProviderCode::Novatel->value,
            'adapter_class' => NullVoipAdapter::class,
            'is_active' => true,
        ]);

        return OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $provider->id,
            'name' => 'Primary',
            'credentials' => ['api_url' => 'https://example.com', 'api_key' => 'x'],
            'is_active' => true,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function createCallLog(OrganizationVoipConnection $connection, array $attributes): VoipCallLog
    {
        return VoipCallLog::query()->create([
            'organization_id' => $connection->organization_id,
            'organization_voip_connection_id' => $connection->id,
            'provider_code' => VoipProviderCode::Novatel->value,
            'status' => CallStatus::Completed,
            'started_at' => now(),
            ...$attributes,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function createWebhookLog(OrganizationVoipConnection $connection, array $attributes): VoipWebhookLog
    {
        return VoipWebhookLog::query()->create([
            'organization_voip_connection_id' => $connection->id,
            'event_type' => VoipWebhookEventType::CallEnded->value,
            'status' => VoipLogStatus::Success,
            'message' => 'ok',
            ...$attributes,
        ]);
    }
}
