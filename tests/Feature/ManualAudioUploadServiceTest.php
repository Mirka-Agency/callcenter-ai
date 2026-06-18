<?php

namespace Tests\Feature;

use App\Application\Call\Services\ManualAudioUploadService;
use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Domain\Call\DTOs\ManualUploadMetadata;
use App\Domain\Call\Enums\UploaderType;
use App\Models\Organization;
use App\Models\PlatformAiSettings;
use App\Models\User;
use App\Support\SampleConversations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualAudioUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_upload_dispatches_async_analysis_instead_of_blocking_request(): void
    {
        Bus::fake();
        Storage::fake('local');
        config(['recordings.disk' => 'local']);

        $sample = collect(SampleConversations::all())->first(fn (array $item) => $item['available'] ?? false);

        $this->assertNotNull($sample, 'Expected at least one available sample conversation.');

        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        PlatformAiSettings::current()->update(['allow_negative_balance' => true]);

        $callId = app(ManualAudioUploadService::class)->uploadFromSample(
            organizationId: $organization->id,
            uploaderUserId: $user->id,
            uploaderType: UploaderType::Employer,
            organizationUserId: null,
            absolutePath: $sample['absolute_path'],
            displayFilename: $sample['filename'],
            metadata: new ManualUploadMetadata(title: $sample['title']),
        );

        Bus::assertDispatched(AnalyzeAudioJob::class, fn (AnalyzeAudioJob $job) => $job->callId === $callId);
    }
}
