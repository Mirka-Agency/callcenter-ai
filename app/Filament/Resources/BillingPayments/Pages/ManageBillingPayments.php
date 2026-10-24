<?php

namespace App\Filament\Resources\BillingPayments\Pages;

use App\Filament\Resources\BillingPayments\BillingPaymentResource;
use Filament\Resources\Pages\ManageRecords;

class ManageBillingPayments extends ManageRecords
{
    protected static string $resource = BillingPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BillingPaymentResource::recordAction(),
        ];
    }
}
