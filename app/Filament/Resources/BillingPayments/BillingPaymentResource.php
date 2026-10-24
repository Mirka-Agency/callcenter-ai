<?php

namespace App\Filament\Resources\BillingPayments;

use App\Domain\Platform\Enums\AdminArea;
use App\Domain\Platform\Enums\InvoiceStatus;
use App\Domain\Platform\Enums\PaymentMethod;
use App\Domain\Platform\Enums\PaymentType;
use App\Filament\Concerns\RequiresAdminArea;
use App\Filament\Resources\BillingInvoices\BillingInvoiceResource;
use App\Filament\Resources\BillingPayments\Pages\ManageBillingPayments;
use App\Filament\Support\CsvExport;
use App\Filament\Support\MoneyInput;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Organization;
use App\Services\Billing\BillingService;
use App\Support\JalaliDate;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use InvalidArgumentException;

class BillingPaymentResource extends Resource
{
    use RequiresAdminArea;

    protected static ?string $model = BillingPayment::class;

    protected static ?string $slug = 'billing-payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 4;

    public static function adminArea(): AdminArea
    {
        return AdminArea::Finance;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.payments');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.finance');
    }

    public static function getModelLabel(): string
    {
        return __('admin.models.payment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.navigation.payments');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['organization:id,title', 'invoice:id,number', 'recordedBy:id,name']))
            ->columns([
                TextColumn::make('paid_at')->label(__('admin.billing.paid_at'))->jalaliDateTime()->sortable(),
                TextColumn::make('organization.title')->label(__('filament.models.organization'))->searchable(),
                TextColumn::make('type')->label(__('admin.billing.payment_type'))->badge()
                    ->formatStateUsing(fn (PaymentType $state): string => $state->label())
                    ->color(fn (PaymentType $state): string => $state->color()),
                MoneyInput::column('amount_rials')->label(__('admin.billing.amount'))->sortable(),
                TextColumn::make('method')->label(__('admin.billing.method'))
                    ->formatStateUsing(fn (PaymentMethod $state): string => $state->label()),
                TextColumn::make('invoice.number')->label(__('admin.billing.invoice_number'))->placeholder('—')
                    ->url(fn (BillingPayment $record): ?string => $record->billing_invoice_id ? BillingInvoiceResource::getUrl('view', ['record' => $record->billing_invoice_id]) : null),
                TextColumn::make('reference')->label(__('admin.billing.reference'))->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('recordedBy.name')->label(__('admin.billing.recorded_by'))->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('admin.billing.payment_type'))->options(PaymentType::options()),
                SelectFilter::make('method')->label(__('admin.billing.method'))->options(PaymentMethod::options()),
                SelectFilter::make('organization_id')->label(__('filament.models.organization'))->relationship('organization', 'title')->searchable(),
            ])
            ->headerActions([
                CsvExport::action('payments', fn (): iterable => self::exportRows()),
            ])
            ->defaultSort('paid_at', 'desc')
            ->persistFiltersInSession();
    }

    public static function recordAction(): Action
    {
        return Action::make('recordPayment')
            ->label(__('admin.billing.record_payment'))
            ->icon(Heroicon::OutlinedPlus)
            ->schema([
                Select::make('organization_id')->label(__('filament.models.organization'))
                    ->options(fn (): array => Organization::query()->where('is_demo', false)->orderBy('title')->pluck('title', 'id')->all())
                    ->searchable()->required()->live(),
                Select::make('billing_invoice_id')->label(__('admin.models.invoice'))
                    ->options(fn (Get $get): array => BillingInvoice::query()
                        ->where('organization_id', $get('organization_id'))
                        ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::Paid->value])
                        ->latest()
                        ->pluck('number', 'id')
                        ->all())
                    ->placeholder(__('admin.billing.no_invoice')),
                Select::make('type')->label(__('admin.billing.payment_type'))->options(PaymentType::options())
                    ->default(PaymentType::Payment->value)->required()->native(false),
                MoneyInput::make('amount_rials')->label(__('admin.billing.amount'))->required(),
                Select::make('method')->label(__('admin.billing.method'))->options(PaymentMethod::options())->required()->native(false),
                DateTimePicker::make('paid_at')->label(__('admin.billing.paid_at'))->jalali()->default(now())->required(),
                TextInput::make('reference')->label(__('admin.billing.reference'))->maxLength(255),
                Textarea::make('notes')->label(__('admin.billing.notes'))->rows(2),
            ])
            ->action(function (array $data): void {
                try {
                    app(BillingService::class)->recordPayment(
                        Organization::query()->findOrFail($data['organization_id']),
                        (int) $data['amount_rials'],
                        PaymentMethod::from($data['method']),
                        invoice: filled($data['billing_invoice_id'] ?? null) ? BillingInvoice::query()->find($data['billing_invoice_id']) : null,
                        paidAt: Carbon::parse($data['paid_at']),
                        reference: $data['reference'] ?? null,
                        type: PaymentType::from($data['type']),
                        notes: $data['notes'] ?? null,
                    );
                    Notification::make()->title(__('admin.common.saved'))->success()->send();
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    /** @return iterable<array<string, mixed>> */
    public static function exportRows(): iterable
    {
        foreach (BillingPayment::query()->with(['organization:id,title', 'invoice:id,number'])->lazyById() as $payment) {
            yield [
                __('admin.billing.paid_at') => JalaliDate::datetime($payment->paid_at),
                __('filament.models.organization') => $payment->organization?->title,
                __('admin.billing.payment_type') => $payment->type->label(),
                __('admin.billing.amount').' (ریال)' => $payment->signedAmount(),
                __('admin.billing.method') => $payment->method->label(),
                __('admin.billing.invoice_number') => $payment->invoice?->number,
                __('admin.billing.reference') => $payment->reference,
            ];
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBillingPayments::route('/'),
        ];
    }
}
