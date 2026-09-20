<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Support\DemoOrganizationCleanupActions;
use App\Filament\Support\DemoUserActions;
use App\Filament\Support\OrganizationWalletActions;
use App\Filament\Widgets\AiManagement\OrganizationWalletStats;
use App\Services\WalletService;
use App\Support\OnPrem;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        app(WalletService::class)->forOrganization($this->getRecord()->id);
    }

    /** @return array<class-string<Widget> | WidgetConfiguration> */
    protected function getHeaderWidgets(): array
    {
        if (OnPrem::billingHidden()) {
            return [];
        }

        return [
            OrganizationWalletStats::make(['organizationId' => $this->getRecord()->id]),
        ];
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            DemoUserActions::addEmployee(),
            DemoOrganizationCleanupActions::deleteRecord(),
            DeleteAction::make()
                ->visible(fn (): bool => ! $this->getRecord()->is_demo),
        ];

        if (! OnPrem::billingHidden()) {
            array_unshift(
                $actions,
                OrganizationWalletActions::addCredits(),
                OrganizationWalletActions::deductCredits(),
            );
        }

        return $actions;
    }
}
