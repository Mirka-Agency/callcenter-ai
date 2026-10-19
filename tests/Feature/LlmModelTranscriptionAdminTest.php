<?php

namespace Tests\Feature;

use App\Domain\Llm\Enums\LlmProviderCode;
use App\Filament\Resources\LlmModels\Pages\CreateLlmModel;
use App\Filament\Resources\LlmModels\Pages\EditLlmModel;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlatformAiSettings;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LlmModelTranscriptionAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_mark_a_model_as_the_transcription_default(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $provider = LlmProvider::query()->create([
            'name' => 'AvalAI',
            'code' => LlmProviderCode::OpenAi->value,
            'api_key' => 'key',
            'base_url' => 'https://api.avalai.ir/v1',
            'is_active' => true,
        ]);

        Livewire::test(CreateLlmModel::class)
            ->assertSee('تبدیل گفتار به متن')
            ->fillForm([
                'provider_id' => $provider->id,
                'name' => 'Whisper',
                'model_key' => 'whisper-1',
                'input_price_per_million_tokens' => '0',
                'output_price_per_million_tokens' => '0',
                'is_default' => false,
                'is_active' => true,
                'is_transcription_default' => true,
                'sends_audio_file' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $model = LlmModel::query()->where('model_key', 'whisper-1')->first();
        $settings = PlatformAiSettings::current()->fresh();

        $this->assertNotNull($model);
        $this->assertTrue($model->is_transcription_default);
        $this->assertSame($model->id, $settings->default_transcription_model_id);
        $this->assertSame($provider->id, $settings->default_transcription_provider_id);
    }

    public function test_editing_the_transcription_default_replaces_the_previous_model(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $provider = LlmProvider::query()->create([
            'name' => 'AvalAI',
            'code' => LlmProviderCode::OpenAi->value,
            'api_key' => 'key',
            'is_active' => true,
        ]);

        $current = LlmModel::query()->create([
            'provider_id' => $provider->id,
            'name' => 'Whisper',
            'model_key' => 'whisper-1',
            'input_price_per_million_tokens' => 0,
            'output_price_per_million_tokens' => 0,
            'is_default' => false,
            'is_transcription_default' => true,
            'is_active' => true,
        ]);
        $replacement = LlmModel::query()->create([
            'provider_id' => $provider->id,
            'name' => 'GPT Transcribe',
            'model_key' => 'gpt-4o-transcribe',
            'input_price_per_million_tokens' => 1,
            'output_price_per_million_tokens' => 2,
            'is_default' => true,
            'is_transcription_default' => false,
            'is_active' => true,
        ]);

        PlatformAiSettings::current()->update([
            'default_llm_provider_id' => $provider->id,
            'default_llm_model_id' => $replacement->id,
            'default_transcription_provider_id' => $provider->id,
            'default_transcription_model_id' => $current->id,
        ]);

        Livewire::test(EditLlmModel::class, ['record' => $replacement->id])
            ->fillForm(['is_transcription_default' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = PlatformAiSettings::current()->fresh();

        $this->assertFalse($current->fresh()->is_transcription_default);
        $this->assertTrue($replacement->fresh()->is_transcription_default);
        $this->assertTrue($replacement->fresh()->is_default);
        $this->assertSame($replacement->id, $settings->default_transcription_model_id);
        $this->assertSame($replacement->id, $settings->default_llm_model_id);
    }
}
