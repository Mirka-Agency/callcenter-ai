<?php

namespace App\Filament\Resources\LlmModels\Pages;

use App\Domain\Billing\Enums\ConversationEstimateType;
use App\Filament\Resources\LlmModels\LlmModelResource;
use App\Models\LlmModel;
use App\Models\PlatformAiSettings;
use App\Services\AiCostEstimatorService;
use App\Support\OnPrem;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class CostEstimator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = LlmModelResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.resources.llm-models.pages.cost-estimator';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.cost_estimator');
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return ! OnPrem::billingHidden() && parent::shouldRegisterNavigation($parameters);
    }

    public static function canAccess(array $parameters = []): bool
    {
        return ! OnPrem::billingHidden() && parent::canAccess($parameters);
    }

    public function getTitle(): string
    {
        return __('filament.pages.ai_cost_estimator');
    }

    public function mount(): void
    {
        $this->form->fill([
            'audio_minutes' => 10,
            'conversation_type' => ConversationEstimateType::ShortSupport->value,
            'custom_output_ratio' => 0.30,
            'selected_model_id' => LlmModel::query()->where('is_active', true)->orderByDesc('is_default')->value('id'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.sections.interactive_estimator'))
                    ->description(__('filament.misc.estimator_description'))
                    ->schema([
                        TextInput::make('audio_minutes')
                            ->label(__('filament.fields.audio_duration_minutes'))
                            ->persianNumeric(null, 1)
                            ->required()
                            ->minValue(0.1)
                            ->step(0.1)
                            ->live(),
                        Select::make('conversation_type')
                            ->label(__('filament.fields.expected_conversation_type'))
                            ->options(ConversationEstimateType::options())
                            ->required()
                            ->live(),
                        TextInput::make('custom_output_ratio')
                            ->label(__('filament.fields.custom_output_ratio'))
                            ->persianNumeric(null, 2)
                            ->minValue(0)
                            ->maxValue(2)
                            ->step(0.01)
                            ->helperText(__('filament.misc.custom_output_ratio_helper'))
                            ->visible(fn (callable $get) => $get('conversation_type') === ConversationEstimateType::Custom->value)
                            ->live(),
                        Select::make('selected_model_id')
                            ->label(__('filament.fields.model_for_estimate'))
                            ->options(fn () => LlmModel::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->live(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function getConversationType(): ConversationEstimateType
    {
        return ConversationEstimateType::tryFrom($this->data['conversation_type'] ?? '')
            ?? ConversationEstimateType::ShortSupport;
    }

    public function getSelectedModel(): ?LlmModel
    {
        $modelId = $this->data['selected_model_id'] ?? null;

        return $modelId
            ? LlmModel::query()->with('provider')->find($modelId)
            : null;
    }

    /** @return array<string, mixed> */
    public function getCurrentEstimate(): array
    {
        $model = $this->getSelectedModel();
        $minutes = (float) ($this->data['audio_minutes'] ?? 0);
        $type = $this->getConversationType();
        $customRatio = $type === ConversationEstimateType::Custom
            ? (float) ($this->data['custom_output_ratio'] ?? 0.30)
            : null;

        if (! $model) {
            return [];
        }

        return app(AiCostEstimatorService::class)->estimateCost($model, $minutes, $type, $customRatio);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function getModelSummaries()
    {
        $type = $this->getConversationType();
        $customRatio = $type === ConversationEstimateType::Custom
            ? (float) ($this->data['custom_output_ratio'] ?? 0.30)
            : null;
        $estimator = app(AiCostEstimatorService::class);

        return LlmModel::query()
            ->with('provider')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (LlmModel $model) => [
                'model' => $model,
                'summary' => $estimator->modelCostSummary($model, $type),
                'custom_ratio' => $customRatio,
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function getSimulationTable()
    {
        $type = $this->getConversationType();
        $customRatio = $type === ConversationEstimateType::Custom
            ? (float) ($this->data['custom_output_ratio'] ?? 0.30)
            : null;

        return app(AiCostEstimatorService::class)->simulateAllModels($type, $customRatio);
    }

    public function formatMoney(float $amount): string
    {
        return PlatformAiSettings::formatMoney($amount);
    }
}
