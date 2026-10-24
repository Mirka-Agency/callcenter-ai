<?php

namespace Tests\Feature;

use App\Application\Transcription\Jobs\CleanupSonioxTranscription;
use App\Application\Transcription\Services\CallTranscriber;
use App\Application\Transcription\Services\SpeakerRoleMapper;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Contracts\CompletesJsonPrompt;
use App\Domain\Llm\Contracts\LlmProviderInterface;
use App\Domain\Llm\DTOs\PromptContextData;
use App\Domain\Llm\Exceptions\LlmRemoteDisabledException;
use App\Domain\Llm\Exceptions\LlmTransientException;
use App\Domain\Llm\ValueObjects\LlmOperationResult;
use App\Domain\Transcription\Contracts\TranscriptionClient;
use App\Domain\Transcription\Exceptions\TranscriptionPending;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\CallTranscript;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SonioxTranscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Bus::fake([CleanupSonioxTranscription::class]);
        Http::preventStrayRequests();
        config(['soniox.api_key' => 'test-key', 'soniox.base_url' => 'https://third.mirka.agency/soniox/v1']);
    }

    public function test_submission_preserves_english_and_uses_the_calls_organization_without_fixed_terms(): void
    {
        $call = $this->callWithRecording();
        $call->organization->update(['title' => 'سازمان نمونه', 'business_context' => 'پشتیبانی نرم‌افزار']);
        Http::fake([
            '*/files' => Http::response(['id' => 'file-1']),
            '*/transcriptions' => Http::response(['id' => 'transcription-1']),
            '*/transcriptions/transcription-1' => Http::response(['status' => 'queued']),
        ]);

        try {
            app(CallTranscriber::class)->transcribe($call);
            $this->fail('Queued transcription must wait for completion.');
        } catch (TranscriptionPending) {
            $this->assertDatabaseHas('call_transcripts', ['call_id' => $call->id, 'remote_transcription_id' => 'transcription-1']);
        }

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/transcriptions')) {
                return false;
            }

            $context = $request['context'];
            $general = array_column($context['general'], 'value', 'key');

            return $general['organization'] === 'سازمان نمونه'
                && $general['domain'] === 'پشتیبانی نرم‌افزار'
                && str_contains($general['instructions'], 'original English spelling when spoken')
                && ! array_key_exists('terms', $context)
                && ! array_key_exists('translation_terms', $context)
                && ! array_key_exists('translation', $request->data())
                && ! ($request['language_hints_strict'] ?? false)
                && $request['language_hints'] === ['fa']
                && $request['enable_speaker_diarization'] === true
                && $request['enable_language_identification'] === true;
        });
    }

    public function test_lost_submission_response_does_not_trigger_another_paid_submission(): void
    {
        $call = $this->callWithRecording();
        Http::fake([
            '*/files' => Http::response(['id' => 'file-1']),
            '*/transcriptions' => Http::failedConnection(),
        ]);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                app(CallTranscriber::class)->transcribe($call);
                $this->fail('Unknown acceptance must require intervention.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('acceptance is unknown', $e->getMessage());
            }
        }
        $this->assertDatabaseHas('call_transcripts', ['call_id' => $call->id, 'status' => 'submitting', 'remote_file_id' => 'file-1']);
        $this->assertCount(1, Http::recorded(fn ($request) => str_ends_with($request->url(), '/files')));
    }

    public function test_submission_server_error_is_not_blindly_retried(): void
    {
        $call = $this->callWithRecording();
        Http::fake(['*/files' => Http::response(['id' => 'file-1']), '*/transcriptions' => Http::response([], 503)]);
        try {
            app(CallTranscriber::class)->transcribe($call);
            $this->fail('Submission acceptance is uncertain.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('acceptance is unknown', $e->getMessage());
        }
        $this->assertDatabaseHas('call_transcripts', ['call_id' => $call->id, 'status' => 'submitting']);
        Http::assertSent(fn ($request) => $request->url() === 'https://third.mirka.agency/soniox/v1/transcriptions');
    }

    public function test_rate_limited_submission_reuses_the_uploaded_file(): void
    {
        $call = $this->callWithRecording();
        Http::fake([
            '*/files' => Http::response(['id' => 'file-1']),
            '*/transcriptions' => Http::sequence()->push(['error_type' => 'rate_limit_exceeded'], 429)->push(['id' => 'transcription-1']),
            '*/transcriptions/transcription-1' => Http::response(['status' => 'queued']),
        ]);
        try {
            app(CallTranscriber::class)->transcribe($call);
            $this->fail('Rate limit should be retryable.');
        } catch (LlmTransientException) {
            $this->assertDatabaseHas('call_transcripts', ['call_id' => $call->id, 'status' => 'pending', 'remote_file_id' => 'file-1']);
        }
        try {
            app(CallTranscriber::class)->transcribe($call);
            $this->fail('Pending transcription should release the queue job.');
        } catch (TranscriptionPending) {
            $this->assertCount(1, Http::recorded(fn ($request) => str_ends_with($request->url(), '/files')));
        }
    }

    public function test_terminal_provider_failure_is_saved_and_schedules_cleanup(): void
    {
        $call = $this->callWithRecording();
        $transcript = $this->submittedTranscript($call);
        Http::fake(['*/transcriptions/transcription-1' => Http::response(['status' => 'failed', 'error_type' => 'file_decode_failed'])]);
        try {
            app(CallTranscriber::class)->transcribe($call);
            $this->fail('Terminal failures must be surfaced.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('file_decode_failed', $e->getMessage());
        }
        $this->assertSame('failed', $transcript->fresh()->status);
        Bus::assertDispatched(CleanupSonioxTranscription::class);
    }

    public function test_deadline_prevents_more_provider_requests(): void
    {
        $call = $this->callWithRecording();
        $transcript = $this->submittedTranscript($call);
        $transcript->update(['submitted_at' => now()->subSeconds(3601)]);
        Http::fake();
        try {
            app(CallTranscriber::class)->transcribe($call);
            $this->fail('An expired transcription must fail.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('deadline', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_missing_soniox_key_prevents_upload(): void
    {
        $call = $this->callWithRecording();
        config(['soniox.api_key' => '']);
        Http::fake();
        try {
            app(CallTranscriber::class)->transcribe($call);
            $this->fail('A key is required.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('SONIOX_API_KEY', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_local_spending_guard_blocks_soniox(): void
    {
        $call = $this->callWithRecording();
        config(['llm.remote_enabled' => false]);
        Http::fake();
        try {
            app(CallTranscriber::class)->transcribe($call);
            $this->fail('Remote calls are disabled.');
        } catch (LlmRemoteDisabledException) {
            Http::assertNothingSent();
        }
    }

    public function test_cleanup_retries_without_losing_the_completed_transcript(): void
    {
        $call = $this->callWithRecording();
        $transcript = $this->submittedTranscript($call);
        $transcript->update(['status' => 'completed', 'content' => 'گوینده 1: سلام']);
        Http::fake([
            '*/transcriptions/transcription-1' => Http::sequence()->push([], 204)->push([], 404),
            '*/files/file-1' => Http::sequence()->push([], 503)->push([], 204),
        ]);
        $job = new CleanupSonioxTranscription($transcript->id);
        try {
            $job->handle(app(TranscriptionClient::class));
            $this->fail('Cleanup must retry after a server failure.');
        } catch (LlmTransientException) {
            $this->assertSame('completed', $transcript->fresh()->status);
            $this->assertNull($transcript->fresh()->remote_cleaned_at);
        }
        $job->handle(app(TranscriptionClient::class));
        $this->assertNotNull($transcript->fresh()->remote_cleaned_at);
        $this->assertSame('گوینده 1: سلام', $transcript->fresh()->content);
    }

    public function test_completed_legacy_transcripts_are_reused_without_soniox(): void
    {
        $call = $this->callWithRecording();
        CallTranscript::query()->create(['call_id' => $call->id, 'organization_id' => $call->organization_id, 'status' => 'completed', 'content' => 'کارشناس: سلام', 'transcription_provider' => 'llm']);
        Http::fake();
        $result = app(CallTranscriber::class)->transcribe($call);
        $this->assertSame('کارشناس: سلام', $result->content);
        Http::assertNothingSent();
    }

    public function test_speaker_mapping_preserves_words_and_boundaries_and_keeps_unknown_roles(): void
    {
        $call = $this->callWithRecording();
        $transcript = $this->submittedTranscript($call);
        $segments = [
            ['speaker' => '1', 'text' => 'سلام Hello', 'start_ms' => 0, 'end_ms' => 100],
            ['speaker' => '2', 'text' => 'سلام', 'start_ms' => 90, 'end_ms' => 200],
            ['speaker' => '3', 'text' => 'صدای سوم', 'start_ms' => 210, 'end_ms' => 300],
            ['speaker' => null, 'text' => 'نامشخص', 'start_ms' => 310, 'end_ms' => 400],
        ];
        $transcript->update(['status' => 'completed', 'content' => 'گوینده 1: سلام Hello', 'segments_json' => $segments]);
        $provider = \Mockery::mock(LlmProviderInterface::class, CompletesJsonPrompt::class);
        $provider->shouldReceive('completeJson')->once()->andReturn(
            LlmOperationResult::success(data: ['roles' => ['1' => 'customer', '2' => 'agent', '3' => 'invalid', '99' => 'agent'], 'transcript' => 'replacement text'])
        );
        $mapper = app(SpeakerRoleMapper::class);
        $context = new PromptContextData;
        $mapper->map($transcript, $provider, 'gemini-3.8-flash', $context);
        $mapper->map($transcript, $provider, 'gemini-3.8-flash', $context);

        $transcript->refresh();
        $this->assertSame($segments, $transcript->segments_json);
        $this->assertSame(['1' => 'customer', '2' => 'agent', '3' => 'unknown'], $transcript->speakers_json);
        $this->assertSame("مشتری: سلام Hello\nکارشناس: سلام\nگوینده 3: صدای سوم\nگوینده نامشخص: نامشخص", $transcript->content);
    }

    public function test_cleanup_command_only_enqueues_terminal_records(): void
    {
        $pending = $this->submittedTranscript($this->callWithRecording());
        $completed = $this->submittedTranscript($this->callWithRecording());
        $completed->update(['status' => 'completed', 'content' => 'سلام']);
        $this->artisan('transcriptions:cleanup')->assertSuccessful();
        Bus::assertDispatched(CleanupSonioxTranscription::class, fn ($job) => $job->transcriptId === $completed->id);
        Bus::assertNotDispatched(CleanupSonioxTranscription::class, fn ($job) => $job->transcriptId === $pending->id);
    }

    private function submittedTranscript(Call $call): CallTranscript
    {
        return CallTranscript::query()->create([
            'call_id' => $call->id,
            'organization_id' => $call->organization_id,
            'transcription_provider' => 'soniox',
            'model_name' => 'stt-async-v5',
            'status' => 'processing',
            'remote_file_id' => 'file-1',
            'remote_transcription_id' => 'transcription-1',
            'submitted_at' => now(),
        ]);
    }

    private function callWithRecording(): Call
    {
        $organization = Organization::factory()->create();
        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'source' => ConversationSource::ManualUpload,
            'provider_code' => 'manual',
            'external_call_id' => uniqid('soniox-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'started_at' => now(),
            'status' => 'completed',
            'duration_seconds' => 60,
        ]);
        Storage::disk('local')->put('recordings/test.mp3', 'audio-bytes');
        CallRecording::query()->create(['call_id' => $call->id, 'storage_disk' => 'local', 'storage_path' => 'recordings/test.mp3', 'status' => 'completed']);

        return $call;
    }
}
