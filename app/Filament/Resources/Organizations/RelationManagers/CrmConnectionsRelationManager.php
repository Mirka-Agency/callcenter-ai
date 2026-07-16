<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Filament\Resources\OrganizationCrmConnections\OrganizationCrmConnectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CrmConnectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'crmConnections';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('filament.relation_managers.crm_connections');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->url(fn ($record) => OrganizationCrmConnectionResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('provider.name')
                    ->label(__('filament.fields.provider'))
                    ->badge(),
                IconColumn::make('is_default')
                    ->label(__('filament.fields.default'))
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label(__('filament.fields.crm_enabled_for_org'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->url(fn () => OrganizationCrmConnectionResource::getUrl('create', [
                        'organization_id' => $this->getOwnerRecord()->getKey(),
                    ])),
            ])
            ->defaultSort('name');
    }
}
