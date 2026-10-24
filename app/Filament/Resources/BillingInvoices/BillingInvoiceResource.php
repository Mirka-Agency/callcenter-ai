<?php

namespace App\Filament\Resources\BillingInvoices;

use App\Domain\Platform\Enums\AdminArea;
use App\Domain\Platform\Enums\InvoiceStatus;
use App\Filament\Concerns\RequiresAdminArea;
use App\Filament\Resources\BillingInvoices\Pages\CreateBillingInvoice;
use App\Filament\Resources\BillingInvoices\Pages\ListBillingInvoices;
use App\Filament\Resources\BillingInvoices\Pages\ViewBillingInvoice;
use App\Filament\Support\CsvExport;
use App\Filament\Support\MoneyInput;
use App\Models\BillingInvoice;
use App\Support\JalaliDate;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BillingInvoiceResource extends Resource
{
    use RequiresAdminArea;

    protected static ?string $model = BillingInvoice::class;

    protected static ?string $slug = 'billing-invoices';

    protected static ?string $recordTitleAttribute = 'number';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 3;

    public static function adminArea(): AdminArea
    {
        return AdminArea::Finance;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.invoices');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.finance');
    }

    public static function getModelLabel(): string
    {
        return __('admin.models.invoice');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.navigation.invoices');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = BillingInvoice::query()->overdue()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'organization.title'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['organization:id,title', 'payments']))
            ->columns([
                TextColumn::make('number')->label(__('admin.billing.invoice_number'))->searchable()->sortable(),
                TextColumn::make('organization.title')->label(__('filament.models.organization'))->searchable(),
                TextColumn::make('status')->label(__('admin.billing.invoice_status'))->badge()
                    ->state(fn (BillingInvoice $record): string => $record->isOverdue() ? __('admin.billing.overdue') : $record->status->label())
                    ->color(fn (BillingInvoice $record): string => $record->isOverdue() ? 'danger' : $record->status->color()),
                MoneyInput::column('total_rials')->label(__('admin.billing.total'))->sortable(),
                TextColumn::make('balance')->label(__('admin.billing.balance'))->alignEnd()
                    ->state(fn (BillingInvoice $record): string => $record->status === InvoiceStatus::Void ? '—' : Money::format($record->balanceRials())),
                TextColumn::make('issued_at')->label(__('admin.billing.issued_at'))->jalaliDate()->sortable()->placeholder('—'),
                TextColumn::make('due_at')->label(__('admin.billing.due_at'))->jalaliDate()->sortable()->placeholder('—'),
                TextColumn::make('paid_at')->label(__('admin.billing.paid_at'))->jalaliDate()->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('admin.billing.invoice_status'))->options(InvoiceStatus::options()),
                Filter::make('overdue')->label(__('admin.billing.overdue'))->query(fn (Builder $query): Builder => $query->overdue()),
                SelectFilter::make('organization_id')->label(__('filament.models.organization'))->relationship('organization', 'title')->searchable(),
            ])
            ->headerActions([
                CsvExport::action('invoices', fn (): iterable => self::exportRows()),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc')
            ->persistFiltersInSession();
    }

    /** @return iterable<array<string, mixed>> */
    public static function exportRows(): iterable
    {
        foreach (BillingInvoice::query()->with(['organization:id,title', 'payments'])->lazyById() as $invoice) {
            yield [
                __('admin.billing.invoice_number') => $invoice->number,
                __('filament.models.organization') => $invoice->organization?->title,
                __('admin.billing.national_id') => $invoice->buyer['national_id'] ?? null,
                __('admin.billing.economic_code') => $invoice->buyer['economic_code'] ?? null,
                __('admin.billing.invoice_status') => $invoice->isOverdue() ? __('admin.billing.overdue') : $invoice->status->label(),
                __('admin.billing.subtotal').' (ریال)' => $invoice->subtotal_rials,
                __('admin.billing.discount').' (ریال)' => $invoice->discount_rials,
                __('admin.billing.tax').' (ریال)' => $invoice->tax_rials,
                __('admin.billing.total').' (ریال)' => $invoice->total_rials,
                __('admin.billing.balance').' (ریال)' => $invoice->balanceRials(),
                __('admin.billing.issued_at') => JalaliDate::date($invoice->issued_at),
                __('admin.billing.due_at') => JalaliDate::date($invoice->due_at),
                __('admin.billing.paid_at') => JalaliDate::date($invoice->paid_at),
            ];
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBillingInvoices::route('/'),
            'create' => CreateBillingInvoice::route('/create'),
            'view' => ViewBillingInvoice::route('/{record}'),
        ];
    }
}
