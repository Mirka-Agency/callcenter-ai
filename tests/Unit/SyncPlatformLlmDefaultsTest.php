<?php

namespace Tests\Unit;

use App\Application\Llm\Actions\SyncPlatformLlmDefaults;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlatformAiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncPlatformLlmDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_model_replaces_the_previous_transcription_default(): void
    {
        $provider = LlmProvider::query()->create([
            'name' => 'OpenAI',
            'code' => LlmProviderCode::OpenAi->value,
            'api_key' => 'key',
            'is_active' => true,
        ]);

        $current = $this->model($provider, 'whisper-1', transcriptionDefault: true);
        $replacement = $this->model($provider, 'gpt-4o-transcribe', transcriptionDefault: true);

        PlatformAiSettings::current()->update([
            'default_transcription_provider_id' => $provider->id,
            'default_transcription_model_id' => $current->id,
        ]);

        app(SyncPlatformLlmDefaults::class)->execute($replacement);

        $settings = PlatformAiSettings::current()->fresh();

        $this->assertFalse($current->fresh()->is_transcription_default);
        $this->assertTrue($replacement->fresh()->is_transcription_default);
        $this->assertSame($replacement->id, $settings->default_transcription_model_id);
        $this->assertSame($provider->id, $settings->default_transcription_provider_id);
    }

    public function test_turning_off_the_transcription_default_clears_the_platform_selection(): void
    {
        $provider = LlmProvider::query()->create([
            'name' => 'OpenAI',
            'code' => LlmProviderCode::OpenAi->value,
            'api_key' => 'key',
            'is_active' => true,
        ]);

        $model = $this->model($provider, 'whisper-1', transcriptionDefault: false);

        PlatformAiSettings::current()->update([
            'default_transcription_provider_id' => $provider->id,
            'default_transcription_model_id' => $model->id,
        ]);

        app(SyncPlatformLlmDefaults::class)->execute($model);

        $settings = PlatformAiSettings::current()->fresh();

        $this->assertNull($settings->default_transcription_model_id);
        $this->assertNull($settings->default_transcription_provider_id);
    }

    private function model(LlmProvider $provider, string $key, bool $transcriptionDefault): LlmModel
    {
        return LlmModel::query()->create([
            'provider_id' => $provider->id,
            'name' => $key,
            'model_key' => $key,
            'input_price_per_million_tokens' => 0,
            'output_price_per_million_tokens' => 0,
            'is_default' => false,
            'is_transcription_default' => $transcriptionDefault,
            'is_active' => true,
        ]);
    }
}
