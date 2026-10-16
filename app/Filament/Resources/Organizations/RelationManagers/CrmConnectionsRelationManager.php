<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Filament\Resources\OrganizationCrmConnections\OrganizationCrmConnectionResource;
use App\Models\Organization;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CrmConnectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'crmConnections';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.relation_managers.crm_connections');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Organization && $ownerRecord->usesExternalCrm();
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
                    ->label(__('filament.fields.active'))
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
