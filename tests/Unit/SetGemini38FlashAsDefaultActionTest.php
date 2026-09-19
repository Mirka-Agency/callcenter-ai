<?php

namespace Tests\Unit;

use App\Application\Llm\Actions\SetGemini38FlashAsDefaultAction;
use App\Domain\Llm\Enums\LlmProviderCode;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\PlatformAiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetGemini38FlashAsDefaultActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_gemini_38_on_avalai_and_makes_it_platform_default(): void
    {
        $openAi = $this->createProvider('OpenAI', LlmProviderCode::OpenAi);
        $avalai = $this->createProvider('avalai', LlmProviderCode::OpenAi);
        $gpt = $this->createModel($openAi, 'gpt-5', isDefault: true);

        PlatformAiSettings::current()->update([
            'default_llm_provider_id' => $openAi->id,
            'default_llm_model_id' => $gpt->id,
        ]);

        $model = app(SetGemini38FlashAsDefaultAction::class)->execute();

        $this->assertNotNull($model);
        $this->assertSame(SetGemini38FlashAsDefaultAction::MODEL_KEY, $model->model_key);
        $this->assertSame($avalai->id, $model->provider_id);
        $this->assertTrue($model->is_default);
        $this->assertFalse($gpt->fresh()->is_default);

        $settings = PlatformAiSettings::current()->fresh();
        $this->assertSame($avalai->id, $settings->default_llm_provider_id);
        $this->assertSame($model->id, $settings->default_llm_model_id);
    }

    public function test_reuses_existing_gemini_38_model_instead_of_creating_another(): void
    {
        $gemini = $this->createProvider('Google Gemini', LlmProviderCode::Gemini);
        $existing = $this->createModel($gemini, SetGemini38FlashAsDefaultAction::MODEL_KEY, isDefault: false);
        $this->createModel($gemini, 'gemini-3.6-flash', isDefault: true);

        $model = app(SetGemini38FlashAsDefaultAction::class)->execute();

        $this->assertSame($existing->id, $model?->id);
        $this->assertSame(1, LlmModel::query()->where('model_key', SetGemini38FlashAsDefaultAction::MODEL_KEY)->count());
        $this->assertTrue($existing->fresh()->is_default);
    }

    public function test_does_nothing_when_no_providers_exist(): void
    {
        $this->assertNull(app(SetGemini38FlashAsDefaultAction::class)->execute());
        $this->assertSame(0, LlmModel::query()->count());
    }

    private function createProvider(string $name, LlmProviderCode $code): LlmProvider
    {
        return LlmProvider::query()->create([
            'name' => $name,
            'code' => $code->value,
            'is_active' => true,
        ]);
    }

    private function createModel(LlmProvider $provider, string $modelKey, bool $isDefault): LlmModel
    {
        return LlmModel::query()->create([
            'provider_id' => $provider->id,
            'name' => $modelKey,
            'model_key' => $modelKey,
            'input_price_per_million_tokens' => 1,
            'output_price_per_million_tokens' => 2,
            'is_default' => $isDefault,
            'is_active' => true,
        ]);
    }
}
