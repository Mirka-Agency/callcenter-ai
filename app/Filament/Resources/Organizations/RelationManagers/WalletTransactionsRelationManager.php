<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Domain\Billing\Enums\WalletTransactionType;
use App\Models\PlatformAiSettings;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WalletTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'walletTransactions';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('filament.relation_managers.wallet_transactions');
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        $currency = $this->getOwnerRecord()->wallet?->currency ?? PlatformAiSettings::currencyCode();

        return $table
            ->columns([
                TextColumn::make('created_at')->jalaliDateTime()->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (WalletTransactionType $state) => $state->label()),
                TextColumn::make('amount')
                    ->money($currency)
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                TextColumn::make('balance_before')
                    ->label(__('filament.fields.balance_before'))
                    ->money($currency),
                TextColumn::make('balance_after')
                    ->label(__('filament.fields.balance_after'))
                    ->money($currency),
                TextColumn::make('description')->limit(40),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
