<?php

namespace Tests\Feature;

use App\Application\Voip\Jobs\ProcessVoipWebhookJob;
use App\Domain\Voip\Enums\VoipProviderCode;
use App\Infrastructure\Voip\Adapters\CustomVoipAdapter;
use App\Models\Organization;
use App\Models\OrganizationVoipConnection;
use App\Models\VoipProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class VoipWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_accepts_payload_when_token_matches(): void
    {
        Bus::fake([ProcessVoipWebhookJob::class]);

        $connection = $this->createConnection();

        $response = $this->postJson(route('webhooks.voip', ['token' => $connection->webhook_token]), [
            'event' => 'call.ended',
            'call_id' => 'abc-123',
        ]);

        $response->assertAccepted()
            ->assertJson(['message' => 'Webhook accepted']);

        Bus::assertDispatched(ProcessVoipWebhookJob::class, function (ProcessVoipWebhookJob $job) use ($connection): bool {
            return $job->connectionId === $connection->id
                && $job->payload['call_id'] === 'abc-123';
        });
    }

    public function test_webhook_rejects_unknown_token(): void
    {
        Bus::fake([ProcessVoipWebhookJob::class]);

        $this->createConnection();

        $response = $this->postJson('/webhooks/voip/'.str_repeat('a', 48), [
            'event' => 'call.ended',
        ]);

        $response->assertNotFound();
        Bus::assertNothingDispatched();
    }

    public function test_employer_can_regenerate_webhook_token(): void
    {
        $employer = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Employer]);
        $organization = Organization::factory()->create(['user_id' => $employer->id]);
        $connection = $this->createConnectionForOrganization($organization);
        $oldToken = $connection->webhook_token;

        $this->actingAs($employer);

        \Livewire\Livewire::test(\App\Livewire\Employer\Voip\Index::class)
            ->call('regenerateWebhookToken', $connection->id);

        $connection->refresh();

        $this->assertNotSame($oldToken, $connection->webhook_token);
        $this->postJson(route('webhooks.voip', ['token' => $oldToken]), ['event' => 'call.ended'])
            ->assertNotFound();
        $this->postJson(route('webhooks.voip', ['token' => $connection->webhook_token]), ['event' => 'call.ended'])
            ->assertAccepted();
    }

    public function test_employer_cannot_regenerate_other_organization_webhook_token(): void
    {
        $employer = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Employer]);
        Organization::factory()->create(['user_id' => $employer->id]);
        $otherOrganization = Organization::factory()->create();
        $connection = $this->createConnectionForOrganization($otherOrganization);

        $this->actingAs($employer);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        \Livewire\Livewire::test(\App\Livewire\Employer\Voip\Index::class)
            ->call('regenerateWebhookToken', $connection->id);
    }

    public function test_inbound_webhook_url_contains_secret_token(): void
    {
        $connection = $this->createConnection();

        $this->assertStringContainsString(
            '/webhooks/voip/'.$connection->webhook_token,
            $connection->inbound_webhook_url,
        );
        $this->assertStringNotContainsString((string) $connection->id, $connection->inbound_webhook_url);
    }

    private function createConnection(): OrganizationVoipConnection
    {
        return $this->createConnectionForOrganization(Organization::factory()->create());
    }

    private function createConnectionForOrganization(Organization $organization): OrganizationVoipConnection
    {
        $provider = VoipProvider::query()->create([
            'name' => 'Custom',
            'code' => VoipProviderCode::Custom->value,
            'adapter_class' => CustomVoipAdapter::class,
            'is_active' => true,
        ]);

        return OrganizationVoipConnection::query()->create([
            'organization_id' => $organization->id,
            'voip_provider_id' => $provider->id,
            'name' => 'Main line',
            'credentials' => [],
            'is_active' => true,
        ]);
    }
}
