<?php

namespace App\Filament\Resources\BillingInvoices\Pages;

use App\Domain\Platform\Enums\InvoiceStatus;
use App\Domain\Platform\Enums\PaymentMethod;
use App\Domain\Platform\Enums\PaymentType;
use App\Filament\Resources\BillingInvoices\BillingInvoiceResource;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Support\MoneyInput;
use App\Models\BillingInvoice;
use App\Models\BillingInvoiceLine;
use App\Services\Billing\BillingService;
use App\Support\JalaliDate;
use App\Support\Money;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/**
 * @property BillingInvoice $record
 */
class ViewBillingInvoice extends ViewRecord
{
    protected static string $resource = BillingInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issue')
                ->label(__('admin.billing.issue'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => $this->record->status === InvoiceStatus::Draft)
                ->requiresConfirmation()
                ->action(fn () => $this->run(fn () => app(BillingService::class)->issue($this->record))),
            self::paymentAction(PaymentType::Payment)
                ->visible(fn (): bool => $this->record->status === InvoiceStatus::Issued),
            self::paymentAction(PaymentType::Refund)
                ->visible(fn (): bool => $this->record->paidRials() > 0),
            Action::make('print')
                ->label(__('admin.billing.print'))
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->visible(fn (): bool => $this->record->status !== InvoiceStatus::Draft)
                ->url(fn (): string => route('admin.billing-invoices.print', $this->record), shouldOpenInNewTab: true),
            Action::make('void')
                ->label(__('admin.billing.void'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(fn (): bool => in_array($this->record->status, [InvoiceStatus::Draft, InvoiceStatus::Issued], true) && $this->record->paidRials() === 0)
                ->requiresConfirmation()
                ->action(fn () => $this->run(fn () => app(BillingService::class)->void($this->record))),
        ];
    }

    private function paymentAction(PaymentType $type): Action
    {
        return Action::make($type === PaymentType::Payment ? 'recordPayment' : 'recordRefund')
            ->label($type === PaymentType::Payment ? __('admin.billing.record_payment') : __('admin.billing.record_refund'))
            ->icon($type === PaymentType::Payment ? Heroicon::OutlinedBanknotes : Heroicon::OutlinedArrowUturnLeft)
            ->color($type === PaymentType::Payment ? 'success' : 'warning')
            ->fillForm(fn (): array => [
                'amount_rials' => $type === PaymentType::Payment ? $this->record->balanceRials() : $this->record->paidRials(),
                'paid_at' => now(),
            ])
            ->schema([
                MoneyInput::make('amount_rials')->label(__('admin.billing.amount'))->required(),
                Select::make('method')->label(__('admin.billing.method'))->options(PaymentMethod::options())->required()->native(false),
                DateTimePicker::make('paid_at')->label(__('admin.billing.paid_at'))->jalali()->required(),
                TextInput::make('reference')->label(__('admin.billing.reference'))->maxLength(255),
            ])
            ->action(fn (array $data) => $this->run(fn () => app(BillingService::class)->recordPayment(
                $this->record->organization,
                (int) $data['amount_rials'],
                PaymentMethod::from($data['method']),
                invoice: $this->record,
                paidAt: Carbon::parse($data['paid_at']),
                reference: $data['reference'] ?? null,
                type: $type,
            )));
    }

    private function run(\Closure $callback): void
    {
        try {
            $callback();
            $this->record->refresh();
            Notification::make()->title(__('admin.common.saved'))->success()->send();
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.models.invoice'))
                ->columns(4)
                ->schema([
                    TextEntry::make('number')->label(__('admin.billing.invoice_number')),
                    TextEntry::make('organization.title')->label(__('filament.models.organization'))
                        ->url(fn (BillingInvoice $record): string => OrganizationResource::getUrl('overview', ['record' => $record->organization_id])),
                    TextEntry::make('status')->label(__('admin.billing.invoice_status'))->badge()
                        ->state(fn (BillingInvoice $record): string => $record->isOverdue() ? __('admin.billing.overdue') : $record->status->label())
                        ->color(fn (BillingInvoice $record): string => $record->isOverdue() ? 'danger' : $record->status->color()),
                    TextEntry::make('subscription.plan.name')->label(__('admin.billing.plan'))->placeholder('—'),
                    TextEntry::make('issued_at')->label(__('admin.billing.issued_at'))->jalaliDate()->placeholder('—'),
                    TextEntry::make('due_at')->label(__('admin.billing.due_at'))->jalaliDate()->placeholder('—'),
                    TextEntry::make('paid_at')->label(__('admin.billing.paid_at'))->jalaliDate()->placeholder('—'),
                    TextEntry::make('notes')->label(__('admin.billing.notes'))->placeholder('—'),
                ]),
            Section::make(__('admin.billing.lines'))
                ->schema([
                    RepeatableEntry::make('lines')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('description')->label(__('admin.billing.description'))->columnSpan(2),
                        TextEntry::make('quantity')->label(__('admin.billing.quantity')),
                        TextEntry::make('amount_rials')->label(__('admin.billing.amount'))
                            ->formatStateUsing(fn (int $state, BillingInvoiceLine $record): string => Money::format($state)),
                    ]),
                ]),
            Section::make(__('admin.billing.totals'))
                ->columns(5)
                ->schema([
                    TextEntry::make('subtotal_rials')->label(__('admin.billing.subtotal'))->formatStateUsing(fn (int $state): string => Money::format($state)),
                    TextEntry::make('discount_rials')->label(__('admin.billing.discount'))->formatStateUsing(fn (int $state): string => Money::format($state)),
                    TextEntry::make('tax_rials')->label(fn (BillingInvoice $record): string => __('admin.billing.tax_with_rate', ['rate' => (float) $record->tax_rate]))
                        ->formatStateUsing(fn (int $state): string => Money::format($state)),
                    TextEntry::make('total_rials')->label(__('admin.billing.total'))->weight('bold')->formatStateUsing(fn (int $state): string => Money::format($state)),
                    TextEntry::make('balance')->label(__('admin.billing.balance'))
                        ->state(fn (BillingInvoice $record): string => Money::format($record->balanceRials())),
                ]),
            Section::make(__('admin.navigation.payments'))
                ->schema([
                    RepeatableEntry::make('payments')->hiddenLabel()->columns(5)->schema([
                        TextEntry::make('type')->label(__('admin.billing.payment_type'))->badge()
                            ->formatStateUsing(fn (PaymentType $state): string => $state->label())->color(fn (PaymentType $state): string => $state->color()),
                        TextEntry::make('amount_rials')->label(__('admin.billing.amount'))->formatStateUsing(fn (int $state): string => Money::format($state)),
                        TextEntry::make('method')->label(__('admin.billing.method'))->formatStateUsing(fn (PaymentMethod $state): string => $state->label()),
                        TextEntry::make('paid_at')->label(__('admin.billing.paid_at'))->formatStateUsing(fn ($state): string => JalaliDate::datetime($state)),
                        TextEntry::make('reference')->label(__('admin.billing.reference'))->placeholder('—'),
                    ])->placeholder(__('admin.billing.no_payments')),
                ]),
        ]);
    }
}
