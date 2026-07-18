<?php

namespace App\Filament\Resources\OrganizationVoipConnections\RelationManagers;

use App\Application\Voip\Jobs\ProcessVoipWebhookJob;
use App\Domain\Voip\Enums\VoipLogStatus;
use App\Domain\Voip\Enums\VoipWebhookEventType;
use App\Models\VoipWebhookLog;
use App\Support\WebhookPayloadPresenter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class WebhookLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'webhookLogs';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.relation_managers.webhook_logs');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_type')
                    ->label(__('filament.fields.event'))
                    ->badge()
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return __('filament.misc.em_dash');
                        }

                        $type = VoipWebhookEventType::tryFrom($state);

                        return $type?->label() ?? $state;
                    })
                    ->placeholder(__('filament.misc.em_dash')),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (VoipLogStatus $state): string => $state->label())
                    ->color(fn (VoipLogStatus $state): string => match ($state) {
                        VoipLogStatus::Success => 'success',
                        VoipLogStatus::Failed => 'danger',
                        VoipLogStatus::Pending => 'warning',
                    }),
                TextColumn::make('message')
                    ->limit(80)
                    ->placeholder(__('filament.misc.em_dash')),
                TextColumn::make('created_at')
                    ->jalaliDateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('viewPayload')
                    ->label(__('filament.actions.view_payload'))
                    ->icon('heroicon-o-code-bracket-square')
                    ->modalHeading(__('filament.sections.webhook_payload'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('filament.actions.close'))
                    ->modalContent(function (VoipWebhookLog $record): View {
                        $presenter = app(WebhookPayloadPresenter::class);

                        return view('filament.components.webhook-payload', [
                            'payload' => $presenter->format($record->payload),
                            'highlights' => $presenter->highlights($record->payload),
                        ]);
                    }),
                Action::make('replay')
                    ->label(__('filament.actions.replay_webhook'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('filament.misc.webhook_replay_description'))
                    ->action(function (VoipWebhookLog $record): void {
                        $payload = $record->payload;

                        if (! is_array($payload) || $payload === []) {
                            Notification::make()
                                ->title(__('filament.misc.webhook_replay_empty'))
                                ->danger()
                                ->send();

                            return;
                        }

                        ProcessVoipWebhookJob::dispatch(
                            $record->organization_voip_connection_id,
                            $payload,
                            forceReplay: true,
                        );

                        Notification::make()
                            ->title(__('filament.misc.webhook_replay_success'))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
