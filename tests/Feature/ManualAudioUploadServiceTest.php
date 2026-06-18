<?php

namespace Tests\Feature;

use App\Application\Call\Services\ManualAudioUploadService;
use App\Application\Intelligence\Jobs\AnalyzeAudioJob;
use App\Domain\Call\DTOs\ManualUploadMetadata;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\UploaderType;
use App\Models\Call;
use App\Models\ConversationAnalysis;
use App\Models\Organization;
use App\Models\User;
use App\Support\SampleConversations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualAudioUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_upload_uses_cached_analysis_instead_of_queue_job(): void
    {
        Bus::fake();
        Storage::fake('local');
        config(['recordings.disk' => 'local']);

        $sample = collect(SampleConversations::all())->first(fn (array $item) => ($item['available'] ?? false) && ($item['cached_analysis'] ?? false));

        $this->assertNotNull($sample, 'Expected at least one available sample conversation with cached analysis.');

        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $callId = app(ManualAudioUploadService::class)->uploadFromSample(
            organizationId: $organization->id,
            uploaderUserId: $user->id,
            uploaderType: UploaderType::Employer,
            organizationUserId: null,
            sampleId: $sample['id'],
            absolutePath: $sample['absolute_path'],
            displayFilename: $sample['filename'],
            metadata: new ManualUploadMetadata(title: $sample['title']),
        );

        Bus::assertNotDispatched(AnalyzeAudioJob::class);

        $call = Call::query()->findOrFail($callId);
        $analysis = ConversationAnalysis::query()->where('call_id', $callId)->first();

        $this->assertSame(CallProcessingStatus::Analyzed, $call->processing_status);
        $this->assertNotNull($analysis);
        $this->assertSame($sample['title'], $call->title);
        $this->assertNotSame('', $analysis->summary);
    }
}
