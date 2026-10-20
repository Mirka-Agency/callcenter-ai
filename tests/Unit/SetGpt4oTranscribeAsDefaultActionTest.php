<?php

namespace Tests\Unit;

use App\Application\Llm\Actions\SetGpt4oTranscribeAsDefaultAction;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlatformAiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetGpt4oTranscribeAsDefaultActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_replaces_whisper_on_the_same_provider(): void
    {
        $openAi = $this->createProvider('OpenAI', LlmProviderCode::OpenAi);
        $whisper = $this->createModel($openAi, 'whisper-1', transcriptionDefault: true);

        PlatformAiSettings::current()->update([
            'default_transcription_provider_id' => $openAi->id,
            'default_transcription_model_id' => $whisper->id,
        ]);

        $model = app(SetGpt4oTranscribeAsDefaultAction::class)->execute();

        $this->assertNotNull($model);
        $this->assertSame(SetGpt4oTranscribeAsDefaultAction::MODEL_KEY, $model->model_key);
        $this->assertSame($openAi->id, $model->provider_id);
        $this->assertTrue($model->is_transcription_default);
        $this->assertTrue($model->sends_audio_file);
        $this->assertFalse($whisper->fresh()->is_transcription_default);

        $settings = PlatformAiSettings::current()->fresh();
        $this->assertSame($openAi->id, $settings->default_transcription_provider_id);
        $this->assertSame($model->id, $settings->default_transcription_model_id);
    }

    public function test_keeps_a_custom_transcription_model(): void
    {
        $provider = $this->createProvider('Speech', LlmProviderCode::Custom);
        $custom = $this->createModel($provider, 'whisper-large-v3', transcriptionDefault: true);

        PlatformAiSettings::current()->update([
            'default_transcription_provider_id' => $provider->id,
            'default_transcription_model_id' => $custom->id,
        ]);

        $model = app(SetGpt4oTranscribeAsDefaultAction::class)->execute();

        $this->assertSame($custom->id, $model?->id);
        $this->assertSame(0, LlmModel::query()->where('model_key', SetGpt4oTranscribeAsDefaultAction::MODEL_KEY)->count());
    }

    public function test_creates_the_model_on_avalai_when_no_transcription_default_exists(): void
    {
        $this->createProvider('Google Gemini', LlmProviderCode::Gemini);
        $avalai = $this->createProvider('avalai', LlmProviderCode::OpenAi);

        $model = app(SetGpt4oTranscribeAsDefaultAction::class)->execute();

        $this->assertNotNull($model);
        $this->assertSame($avalai->id, $model->provider_id);
        $this->assertSame($model->id, PlatformAiSettings::current()->fresh()->default_transcription_model_id);
    }

    public function test_reuses_an_existing_gpt4o_transcribe_model(): void
    {
        $openAi = $this->createProvider('OpenAI', LlmProviderCode::OpenAi);
        $existing = $this->createModel($openAi, SetGpt4oTranscribeAsDefaultAction::MODEL_KEY, transcriptionDefault: false);

        $model = app(SetGpt4oTranscribeAsDefaultAction::class)->execute();

        $this->assertSame($existing->id, $model?->id);
        $this->assertSame(1, LlmModel::query()->where('model_key', SetGpt4oTranscribeAsDefaultAction::MODEL_KEY)->count());
        $this->assertTrue($existing->fresh()->is_transcription_default);
        $this->assertTrue($existing->fresh()->sends_audio_file);
    }

    private function createProvider(string $name, LlmProviderCode $code): LlmProvider
    {
        return LlmProvider::query()->create([
            'name' => $name,
            'code' => $code->value,
            'is_active' => true,
        ]);
    }

    private function createModel(LlmProvider $provider, string $modelKey, bool $transcriptionDefault): LlmModel
    {
        return LlmModel::query()->create([
            'provider_id' => $provider->id,
            'name' => $modelKey,
            'model_key' => $modelKey,
            'input_price_per_million_tokens' => 0,
            'output_price_per_million_tokens' => 0,
            'is_default' => false,
            'is_transcription_default' => $transcriptionDefault,
            'is_active' => true,
            'sends_audio_file' => true,
        ]);
    }
}
