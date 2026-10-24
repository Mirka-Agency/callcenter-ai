<?php

namespace App\Filament\Resources\ConversationAnalyses\Pages;

use App\Domain\Call\Enums\ConversationSource;
use App\Domain\Llm\Enums\AnalysisSentiment;
use App\Filament\Resources\ConversationAnalyses\ConversationAnalysisResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewConversationAnalysis extends ViewRecord
{
    protected static string $resource = ConversationAnalysisResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.sections.usage'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('source')
                            ->label(__('filament.fields.source'))
                            ->badge()
                            ->formatStateUsing(fn (?ConversationSource $state): ?string => $state?->label()),
                        TextEntry::make('organization.title')->label(__('filament.fields.organization')),
                        TextEntry::make('employee.full_name')->label(__('filament.fields.employee'))->placeholder(__('filament.misc.em_dash')),
                        TextEntry::make('analyzed_at')
                            ->label(__('filament.fields.call_date'))
                            ->jalaliDateTime(),
                        TextEntry::make('llm_provider')->label(__('filament.fields.provider')),
                        TextEntry::make('model_name')->label(__('filament.fields.model')),
                        TextEntry::make('prompt_version')
                            ->label(__('filament.fields.prompt_version'))
                            ->placeholder(__('filament.misc.em_dash')),
                        TextEntry::make('processing_duration_ms')->label(__('filament.fields.processing_ms')),
                        TextEntry::make('score')
                            ->label(__('filament.fields.score'))
                            ->badge(),
                        TextEntry::make('sentiment')
                            ->label(__('filament.fields.sentiment'))
                            ->badge()
                            ->formatStateUsing(fn (?AnalysisSentiment $state): ?string => $state?->label())
                            ->color(fn (?AnalysisSentiment $state): string|array|null => $state?->color()),
                    ]),
                Section::make(__('filament.sections.analysis'))
                    ->schema([
                        TextEntry::make('summary')
                            ->label(__('filament.fields.summary'))
                            ->columnSpanFull(),
                        TextEntry::make('overall_evaluation')
                            ->label(__('filament.fields.overall_evaluation'))
                            ->columnSpanFull()
                            ->placeholder(__('filament.misc.em_dash')),
                        TextEntry::make('strengths_json')
                            ->label(__('filament.fields.strengths'))
                            ->listWithLineBreaks()
                            ->bulleted(),
                        TextEntry::make('weaknesses_json')
                            ->label(__('filament.fields.weaknesses'))
                            ->listWithLineBreaks()
                            ->bulleted(),
                        TextEntry::make('next_actions_json')
                            ->label(__('filament.fields.next_actions'))
                            ->listWithLineBreaks()
                            ->bulleted(),
                    ]),
                Section::make(__('filament.sections.lead_quality'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('lead_quality_json.score')
                            ->label(__('filament.fields.lead_score'))
                            ->numeric()
                            ->placeholder(__('filament.misc.em_dash')),
                        TextEntry::make('lead_quality_json.level')
                            ->label(__('filament.fields.lead_level'))
                            ->formatStateUsing(fn (?string $state) => match ($state) {
                                'high' => 'بالا',
                                'medium' => 'متوسط',
                                'low' => 'کم',
                                default => $state ?? __('filament.misc.em_dash'),
                            }),
                        TextEntry::make('lead_quality_json.reason')
                            ->label(__('filament.fields.lead_reason'))
                            ->columnSpanFull()
                            ->placeholder(__('filament.misc.em_dash')),
                        TextEntry::make('lead_quality_json.buying_intent_signals')
                            ->label(__('filament.fields.buying_intent_signals'))
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->columnSpanFull()
                            ->placeholder(__('filament.misc.em_dash')),
                    ]),
                Section::make(__('filament.sections.concerns'))
                    ->schema([
                        TextEntry::make('concerns_json')
                            ->hiddenLabel()
                            ->formatStateUsing(function (?array $state): string {
                                if (empty($state)) {
                                    return __('filament.misc.em_dash');
                                }

                                $typeLabels = [
                                    'price' => 'قیمت',
                                    'trust' => 'اعتماد',
                                    'timing' => 'زمان‌بندی',
                                    'technical' => 'فنی',
                                    'other' => 'سایر',
                                ];
                                $severityLabels = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'بالا'];

                                return collect($state)->map(function (array $concern) use ($typeLabels, $severityLabels): string {
                                    $type = $typeLabels[$concern['type'] ?? 'other'] ?? 'سایر';
                                    $severity = $severityLabels[$concern['severity'] ?? 'medium'] ?? 'متوسط';

                                    return "{$type} ({$severity}): ".($concern['text'] ?? '');
                                })->implode("\n");
                            })
                            ->columnSpanFull(),
                    ]),
                Section::make(__('filament.sections.customer_identity'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('customer_identity_json.person_name')
                            ->label(__('filament.fields.customer_person_name'))
                            ->placeholder(__('filament.misc.em_dash')),
                        TextEntry::make('customer_identity_json.company_name')
                            ->label(__('filament.fields.customer_company_name'))
                            ->placeholder(__('filament.misc.em_dash')),
                        TextEntry::make('customer_identity_json.confidence')
                            ->label(__('filament.fields.identity_confidence'))
                            ->formatStateUsing(fn (?float $state) => $state !== null ? number_format($state * 100, 0).'%' : __('filament.misc.em_dash')),
                        TextEntry::make('customer_identity_json.evidence')
                            ->label(__('filament.fields.identity_evidence'))
                            ->columnSpanFull()
                            ->placeholder(__('filament.misc.em_dash')),
                    ]),
            ]);
    }
}
