<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Support\DemoOrganizationCleanupActions;
use App\Filament\Support\DemoUserActions;
use App\Filament\Support\OrganizationWalletActions;
use App\Filament\Widgets\AiManagement\OrganizationWalletStats;
use App\Services\WalletService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        app(WalletService::class)->forOrganization($this->getRecord()->id);
    }

    /** @return array<class-string<\Filament\Widgets\Widget> | \Filament\Widgets\WidgetConfiguration> */
    protected function getHeaderWidgets(): array
    {
        return [
            OrganizationWalletStats::make(['organizationId' => $this->getRecord()->id]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            OrganizationWalletActions::addCredits(),
            OrganizationWalletActions::deductCredits(),
            DemoUserActions::addEmployee(),
            DemoOrganizationCleanupActions::deleteRecord(),
            DeleteAction::make()
                ->visible(fn (): bool => ! $this->getRecord()->is_demo),
        ];
    }
}
