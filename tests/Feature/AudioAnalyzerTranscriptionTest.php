<?php

namespace Tests\Feature;

use App\Application\Llm\AnalysisManager;
use App\Domain\Call\Enums\CallProcessingStatus;
use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\PlatformAiSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioAnalyzerTranscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_is_transcribed_completely_before_the_transcript_is_analyzed(): void
    {
        Storage::fake('local');
        $call = $this->seedCall();
        $this->seedPlatformLlm();

        $transcript = "کارشناس: سلام، شرکت آوایار هستم.\nمشتری: سفارشم هنوز نرسیده است.";
        $analysisSentTranscript = false;
        $analysisSentAudio = false;

        Http::fake(function ($request) use (&$analysisSentTranscript, &$analysisSentAudio) {
            if (str_contains($request->url(), '/audio/transcriptions')) {
                return Http::response([
                    'segments' => [
                        ['speaker' => 'agent', 'text' => 'سلام، شرکت آوایار هستم.'],
                        ['speaker' => 'customer', 'text' => 'سفارشم هنوز نرسیده است.'],
                    ],
                ]);
            }

            $encoded = json_encode($request->data(), JSON_UNESCAPED_UNICODE) ?: '';
            $analysisSentTranscript = str_contains($encoded, 'کارشناس: سلام، شرکت آوایار هستم.')
                && str_contains($encoded, 'مشتری: سفارشم هنوز نرسیده است.')
                && str_contains($encoded, 'فقط متن مکالمه بالا را تحلیل کنید.');
            $analysisSentAudio = str_contains($encoded, 'input_audio');

            return Http::response([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['content' => json_encode([
                        'score' => 70,
                        'summary' => 'مشتری پیگیر نرسیدن سفارش بود و کارشناس بررسی را پذیرفت.',
                        'sentiment' => 'negative',
                        'strengths' => ['پاسخ روشن'],
                        'weaknesses' => [],
                        'next_actions' => [],
                        'evaluable' => true,
                    ], JSON_UNESCAPED_UNICODE)],
                ]],
                'usage' => ['prompt_tokens' => 40, 'completion_tokens' => 20],
            ]);
        });

        $result = AnalysisManager::forOrganization($call->organization_id)->analyzeCall($call->id);

        $this->assertTrue($analysisSentTranscript);
        $this->assertFalse($analysisSentAudio);
        $this->assertSame($transcript, $result->transcript);
        $this->assertDatabaseHas('call_transcripts', [
            'call_id' => $call->id,
            'content' => $transcript,
            'status' => 'completed',
            'model_name' => 'whisper-1',
        ]);
        $this->assertDatabaseHas('conversation_analyses', [
            'call_id' => $call->id,
            'transcript' => $transcript,
            'summary' => 'مشتری پیگیر نرسیدن سفارش بود و کارشناس بررسی را پذیرفت.',
        ]);
        $this->assertSame(CallProcessingStatus::Analyzing, $call->fresh()->processing_status);
    }

    public function test_selected_transcription_model_uses_its_own_provider(): void
    {
        Storage::fake('local');
        $call = $this->seedCall();
        $this->seedPlatformLlm();

        $analysisModel = LlmModel::query()->where('is_default', true)->firstOrFail();
        $analysisModel->provider->update(['base_url' => 'https://analysis.test/v1']);

        $sttProvider = LlmProvider::query()->create([
            'name' => 'Speech',
            'code' => LlmProviderCode::OpenAi->value,
            'api_key' => 'stt-key',
            'base_url' => 'https://stt.test/v1',
            'is_active' => true,
        ]);

        $sttModel = LlmModel::query()->create([
            'provider_id' => $sttProvider->id,
            'name' => 'Whisper Large',
            'model_key' => 'whisper-large-v3',
            'input_price_per_million_tokens' => 0,
            'output_price_per_million_tokens' => 0,
            'is_default' => false,
            'is_transcription_default' => true,
            'is_active' => true,
            'sends_audio_file' => true,
        ]);

        PlatformAiSettings::current()->update([
            'default_transcription_provider_id' => $sttProvider->id,
            'default_transcription_model_id' => $sttModel->id,
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/audio/transcriptions')) {
                $this->assertStringContainsString('https://stt.test/v1/audio/transcriptions', $request->url());
                $this->assertStringContainsString('whisper-large-v3', $request->body());

                return Http::response(['text' => 'کارشناس: سلام']);
            }

            $this->assertStringContainsString('https://analysis.test/v1/chat/completions', $request->url());
            $this->assertStringContainsString('gpt-4o', json_encode($request->data(), JSON_UNESCAPED_UNICODE) ?: '');

            return Http::response([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['content' => json_encode([
                        'score' => 70,
                        'summary' => 'خلاصه تماس.',
                        'sentiment' => 'neutral',
                        'strengths' => [],
                        'weaknesses' => [],
                        'next_actions' => [],
                        'evaluable' => true,
                    ], JSON_UNESCAPED_UNICODE)],
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]);
        });

        AnalysisManager::forOrganization($call->organization_id)->analyzeCall($call->id);

        $this->assertDatabaseHas('call_transcripts', [
            'call_id' => $call->id,
            'content' => 'کارشناس: سلام',
            'model_name' => 'whisper-large-v3',
        ]);
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

    private function seedPlatformLlm(): void
    {
        $provider = LlmProvider::query()->create([
            'name' => 'OpenAI',
            'code' => LlmProviderCode::OpenAi->value,
            'api_key' => 'test-key',
            'is_active' => true,
        ]);

        $model = LlmModel::query()->create([
            'provider_id' => $provider->id,
            'name' => 'gpt-4o',
            'model_key' => 'gpt-4o',
            'input_price_per_million_tokens' => 1,
            'output_price_per_million_tokens' => 2,
            'is_default' => true,
            'is_active' => true,
            'sends_audio_file' => true,
        ]);

        PlatformAiSettings::current()->update([
            'default_llm_provider_id' => $provider->id,
            'default_llm_model_id' => $model->id,
            'allow_negative_balance' => true,
        ]);
    }
}
