<?php

namespace Tests\Feature;

use App\Application\Llm\AnalysisManager;
use App\Application\Transcription\Jobs\CleanupSonioxTranscription;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Transcription\Exceptions\TranscriptionPending;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\CallTranscript;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioAnalyzerTranscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Bus::fake([CleanupSonioxTranscription::class]);
        Http::preventStrayRequests();
        config([
            'llm.prompt_cache' => false,
            'llm.provider' => 'gemini',
            'llm.api_key' => 'analysis-key',
            'llm.model' => 'gemini-3.8-flash',
            'llm.base_url' => 'https://analysis.test/v1beta',
            'soniox.api_key' => 'speech-key',
            'soniox.base_url' => 'https://speech.test/v1',
        ]);
    }

    public function test_soniox_speech_is_stored_then_analyzed_without_sending_audio_to_the_llm(): void
    {
        $call = $this->seedCall();
        $this->fakeProviders();

        $result = AnalysisManager::forOrganization($call->organization_id)->analyzeCall($call->id);
        $transcript = CallTranscript::query()->where('call_id', $call->id)->firstOrFail();

        $this->assertSame("کارشناس: سلام، شرکت آوایار هستم.\nمشتری: سفارشم هنوز نرسیده است.", $result->transcript);
        $this->assertSame('soniox', $transcript->transcription_provider);
        $this->assertSame('stt-async-v5', $transcript->model_name);
        $this->assertSame('file-1', $transcript->remote_file_id);
        $this->assertSame('transcription-1', $transcript->remote_transcription_id);
        $this->assertCount(2, $transcript->segments_json);
        $this->assertSame(0.95, $transcript->segments_json[0]['tokens'][0]['confidence']);
        $this->assertSame('gemini-3.8-flash', $result->modelName);
        $this->assertSame('gemini', $result->llmProvider);
        $this->assertNull($result->llmModelId);
        $this->assertDatabaseHas('conversation_analyses', ['call_id' => $call->id, 'llm_model_id' => null]);
        Bus::assertDispatched(CleanupSonioxTranscription::class);
        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/transcriptions') && $request['language_hints'] === ['fa']
                && $request['enable_speaker_diarization'] === true && $request['model'] === 'stt-async-v5'
                && $request->hasHeader('Authorization', 'Bearer speech-key');
        });
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'analysis.test') && str_contains(json_encode($request->data()), 'inline_data');
        });
    }

    public function test_analysis_retries_reuse_transcription_and_speaker_mapping(): void
    {
        $call = $this->seedCall();
        $this->fakeProviders(failAnalysis: true);
        try {
            AnalysisManager::forOrganization($call->organization_id)->analyzeCall($call->id);
            $this->fail('Analysis should fail.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('503', $e->getMessage());
        }
        $this->assertDatabaseHas('call_transcripts', ['call_id' => $call->id, 'status' => 'completed']);
        $this->fakeProviders();
        AnalysisManager::forOrganization($call->organization_id)->analyzeCall($call->id);

        $submissions = Http::recorded(fn ($request) => str_ends_with($request->url(), '/transcriptions'));
        $this->assertCount(0, $submissions);
        $mappings = Http::recorded(fn ($request) => str_contains(json_encode($request->data()), 'Map speaker IDs'));
        $this->assertCount(0, $mappings);
    }

    public function test_pending_soniox_work_does_not_invoke_the_llm(): void
    {
        $call = $this->seedCall();
        $this->fakeProviders(status: 'transcribing');
        try {
            AnalysisManager::forOrganization($call->organization_id)->analyzeCall($call->id);
            $this->fail('Pending transcription must suspend analysis.');
        } catch (TranscriptionPending) {
            $this->assertDatabaseMissing('conversation_analyses', ['call_id' => $call->id]);
        }
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'analysis.test'));
        $this->fakeProviders();
        AnalysisManager::forOrganization($call->organization_id)->analyzeCall($call->id);
        $this->assertCount(0, Http::recorded(fn ($request) => str_ends_with($request->url(), '/transcriptions')));
    }

    public function test_missing_llm_configuration_prevents_audio_submission(): void
    {
        $call = $this->seedCall();
        config(['llm.api_key' => '']);
        Http::fake();
        try {
            AnalysisManager::forOrganization($call->organization_id)->analyzeCall($call->id);
            $this->fail('Missing configuration must fail.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('LLM_API_KEY', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    private function fakeProviders(string $status = 'completed', bool $failAnalysis = false): void
    {
        config(['test.soniox_status' => $status, 'test.fail_analysis' => $failAnalysis]);
        Http::fake(function ($request) {
            $status = config('test.soniox_status');
            $failAnalysis = config('test.fail_analysis');
            $url = $request->url();
            if (str_ends_with($url, '/files')) {
                return Http::response(['id' => 'file-1']);
            }
            if (str_ends_with($url, '/transcriptions')) {
                return Http::response(['id' => 'transcription-1']);
            }
            if (str_ends_with($url, '/transcript')) {
                return Http::response(['tokens' => [
                    ['text' => 'سلام، شرکت آوایار هستم.', 'speaker' => '1', 'start_ms' => 0, 'end_ms' => 1000, 'confidence' => 0.95],
                    ['text' => 'سفارشم هنوز نرسیده است.', 'speaker' => '2', 'start_ms' => 1100, 'end_ms' => 2000],
                ]]);
            }
            if (str_contains($url, '/transcriptions/transcription-1')) {
                return Http::response(['status' => $status]);
            }
            $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);
            if (str_contains($body, 'Map speaker IDs')) {
                $payload = ['roles' => ['1' => 'agent', '2' => 'customer']];
            } elseif (str_contains($body, 'تو فقط تماس را دسته‌بندی')) {
                $payload = ['kind' => 'real', 'confidence' => 1];
            } else {
                if ($failAnalysis) {
                    return Http::response(['error' => 'service_unavailable'], 503);
                }
                $this->assertSame('completed', CallTranscript::query()->firstOrFail()->status);
                $this->assertStringContainsString('کارشناس: سلام، شرکت آوایار هستم.', $body);
                $payload = ['score' => 70, 'summary' => 'مشتری پیگیر سفارش بود.', 'sentiment' => 'negative', 'strengths' => [], 'weaknesses' => [], 'next_actions' => [], 'evaluable' => true];
            }
            $this->assertSame('low', $request['generationConfig']['thinkingConfig']['thinkingLevel']);

            return Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($payload, JSON_UNESCAPED_UNICODE)]]], 'finishReason' => 'STOP']],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
            ]);
        });
    }

    private function seedCall(): Call
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $employee = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'first_name' => 'سارا',
            'last_name' => 'کریمی',
            'is_active' => true,
        ]);

        $call = Call::query()->create([
            'organization_id' => $organization->id,
            'organization_user_id' => $employee->id,
            'source' => ConversationSource::ManualUpload,
            'provider_code' => 'manual',
            'external_call_id' => uniqid('call-', true),
            'direction' => 'inbound',
            'caller_number' => '09120000000',
            'receiver_number' => '02100000000',
            'status' => 'completed',
            'processing_status' => CallProcessingStatus::Pending,
            'duration_seconds' => 120,
            'started_at' => now(),
        ]);

        Storage::disk('local')->put('recordings/test.mp3', 'audio-bytes');

        CallRecording::query()->create([
            'call_id' => $call->id,
            'storage_disk' => 'local',
            'storage_path' => 'recordings/test.mp3',
            'mime_type' => 'audio/mpeg',
            'file_size_bytes' => 11,
            'status' => 'completed',
        ]);

        return $call;
    }
}
