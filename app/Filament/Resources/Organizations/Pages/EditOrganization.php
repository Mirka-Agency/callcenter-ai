<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Support\DemoOrganizationCleanupActions;
use App\Filament\Support\DemoUserActions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DemoUserActions::addEmployee(),
            DemoOrganizationCleanupActions::deleteRecord(),
            DeleteAction::make()
                ->visible(fn (): bool => ! $this->getRecord()->is_demo),
        ];
    }
}
