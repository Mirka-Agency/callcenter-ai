<?php

namespace App\Filament\Resources\BillingInvoices\Pages;

use App\Filament\Resources\BillingInvoices\BillingInvoiceResource;
use App\Filament\Support\MoneyInput;
use App\Models\Organization;
use App\Services\Billing\BillingService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateBillingInvoice extends CreateRecord
{
    protected static string $resource = BillingInvoiceResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('organization_id')->label(__('filament.models.organization'))
                ->options(fn (): array => Organization::query()->where('is_demo', false)->orderBy('title')->pluck('title', 'id')->all())
                ->searchable()->required(),
            MoneyInput::make('discount_rials')->label(__('admin.billing.discount'))->default(0),
            Repeater::make('lines')->label(__('admin.billing.lines'))
                ->schema([
                    TextInput::make('description')->label(__('admin.billing.description'))->required()->maxLength(255)->columnSpan(2),
                    TextInput::make('quantity')->label(__('admin.billing.quantity'))->numeric()->minValue(1)->default(1)->required(),
                    MoneyInput::make('unit_price_rials')->label(__('admin.billing.unit_price'))->required(),
                ])
                ->columns(4)
                ->minItems(1)
                ->defaultItems(1)
                ->columnSpanFull(),
            Textarea::make('notes')->label(__('admin.billing.notes'))->rows(2)->columnSpanFull(),
            Toggle::make('issue')->label(__('admin.billing.issue_now'))->helperText(__('admin.billing.issue_now_help'))->default(true),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(BillingService::class)->createInvoice(
            Organization::query()->findOrFail($data['organization_id']),
            array_values($data['lines']),
            discountRials: (int) ($data['discount_rials'] ?? 0),
            issue: (bool) ($data['issue'] ?? false),
            notes: $data['notes'] ?? null,
        );
    }

    protected function getRedirectUrl(): string
    {
        return BillingInvoiceResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
