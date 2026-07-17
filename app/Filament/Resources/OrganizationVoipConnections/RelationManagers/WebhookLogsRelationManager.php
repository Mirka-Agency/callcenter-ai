<?php

namespace App\Filament\Resources\OrganizationVoipConnections\RelationManagers;

use App\Domain\Voip\Enums\VoipLogStatus;
use App\Models\VoipWebhookLog;
use App\Support\WebhookPayloadPresenter;
use Filament\Actions\Action;
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
                    ->modalContent(fn (VoipWebhookLog $record): View => view(
                        'filament.components.webhook-payload',
                        ['payload' => app(WebhookPayloadPresenter::class)->format($record->payload)],
                    )),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
