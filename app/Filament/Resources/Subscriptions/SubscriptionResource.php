<?php

namespace App\Filament\Resources\Subscriptions;

use App\Domain\Platform\Enums\AdminArea;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Filament\Concerns\RequiresAdminArea;
use App\Filament\Resources\BillingInvoices\BillingInvoiceResource;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Resources\Subscriptions\Pages\ManageSubscriptions;
use App\Filament\Support\CsvExport;
use App\Filament\Support\MoneyInput;
use App\Models\Coupon;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\BillingService;
use App\Support\JalaliDate;
use App\Support\Money;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use InvalidArgumentException;

class SubscriptionResource extends Resource
{
    use RequiresAdminArea;

    protected static ?string $model = Subscription::class;

    protected static ?string $slug = 'subscriptions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?int $navigationSort = 2;

    public static function adminArea(): AdminArea
    {
        return AdminArea::Finance;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.subscriptions');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament.navigation.groups.finance');
    }

    public static function getModelLabel(): string
    {
        return __('admin.models.subscription');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.navigation.subscriptions');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['organization:id,title', 'plan:id,name', 'coupon:id,code']))
            ->columns([
                TextColumn::make('organization.title')->label(__('filament.models.organization'))->searchable()
                    ->url(fn (Subscription $record): string => OrganizationResource::getUrl('overview', ['record' => $record->organization_id])),
                TextColumn::make('plan.name')->label(__('admin.billing.plan')),
                TextColumn::make('status')->label(__('admin.billing.subscription_status'))->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label())
                    ->color(fn (SubscriptionStatus $state): string => $state->color()),
                MoneyInput::column('price_rials')->label(__('admin.billing.price_per_period')),
                TextColumn::make('mrr')->label(__('admin.billing.mrr'))->alignEnd()
                    ->state(fn (Subscription $record): string => Money::format($record->ended_at?->isPast() ? 0 : $record->monthlyRevenue())),
                TextColumn::make('coupon.code')->label(__('admin.billing.coupon'))->badge()->color('gray')->placeholder('—')->toggleable(),
                TextColumn::make('starts_at')->label(__('admin.billing.starts_at'))->jalaliDate()->sortable(),
                TextColumn::make('current_period_ends_at')->label(__('admin.billing.period_ends_at'))->jalaliDate()->sortable()
                    ->color(fn (Subscription $record): ?string => $record->current_period_ends_at?->isBefore(now()->addDays(14)) && $record->ended_at === null ? 'warning' : null),
                TextColumn::make('ended_at')->label(__('admin.billing.ended_at'))->jalaliDate()->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('admin.billing.subscription_status'))->options(SubscriptionStatus::options())->multiple()
                    ->default([SubscriptionStatus::Trial->value, SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value]),
                SelectFilter::make('plan_id')->label(__('admin.billing.plan'))->relationship('plan', 'name'),
            ])
            ->headerActions([
                CsvExport::action('subscriptions', fn (): iterable => self::exportRows()),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('convertTrial')
                        ->label(__('admin.billing.convert_trial'))
                        ->icon(Heroicon::OutlinedCheckBadge)
                        ->color('success')
                        ->visible(fn (Subscription $record): bool => $record->status === SubscriptionStatus::Trial)
                        ->requiresConfirmation()
                        ->action(fn (Subscription $record) => self::done(app(BillingService::class)->convertTrial($record))),
                    Action::make('renew')
                        ->label(__('admin.billing.renew'))
                        ->icon(Heroicon::OutlinedDocumentPlus)
                        ->visible(fn (Subscription $record): bool => in_array($record->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true))
                        ->requiresConfirmation()
                        ->modalDescription(__('admin.billing.renew_description'))
                        ->action(function (Subscription $record) {
                            $invoice = app(BillingService::class)->renew($record);

                            return redirect(BillingInvoiceResource::getUrl('view', ['record' => $invoice]));
                        }),
                    Action::make('issueInvoice')
                        ->label(__('admin.billing.issue_current_invoice'))
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->visible(fn (Subscription $record): bool => in_array($record->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true))
                        ->requiresConfirmation()
                        ->action(function (Subscription $record) {
                            $end = $record->current_period_ends_at ?? now();
                            $invoice = app(BillingService::class)->invoiceForPeriod($record, $end->copy()->subMonths($record->billing_period->months()), $end);

                            return redirect(BillingInvoiceResource::getUrl('view', ['record' => $invoice]));
                        }),
                    Action::make('changePlan')
                        ->label(__('admin.billing.change_plan'))
                        ->icon(Heroicon::OutlinedArrowsRightLeft)
                        ->visible(fn (Subscription $record): bool => $record->ended_at === null)
                        ->schema([
                            Select::make('plan_id')->label(__('admin.billing.plan'))
                                ->options(fn (): array => Plan::query()->where('is_active', true)->orderBy('sort')->pluck('name', 'id')->all())
                                ->required()->native(false),
                        ])
                        ->action(fn (Subscription $record, array $data) => self::done(app(BillingService::class)->subscribe(
                            $record->organization,
                            Plan::query()->findOrFail($data['plan_id']),
                            coupon: $record->coupon?->isRedeemable() ? $record->coupon : null,
                        ))),
                    Action::make('cancel')
                        ->label(__('admin.billing.cancel'))
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('danger')
                        ->visible(fn (Subscription $record): bool => $record->cancelled_at === null && $record->ended_at === null)
                        ->schema([
                            Toggle::make('immediately')->label(__('admin.billing.cancel_immediately'))->helperText(__('admin.billing.cancel_immediately_help')),
                        ])
                        ->action(fn (Subscription $record, array $data) => self::done(app(BillingService::class)->cancel($record, (bool) ($data['immediately'] ?? false)))),
                ]),
            ])
            ->defaultSort('starts_at', 'desc')
            ->persistFiltersInSession();
    }

    public static function createAction(): Action
    {
        return Action::make('subscribe')
            ->label(__('admin.billing.new_subscription'))
            ->icon(Heroicon::OutlinedPlus)
            ->schema([
                Select::make('organization_id')->label(__('filament.models.organization'))
                    ->options(fn (): array => Organization::query()->where('is_demo', false)->orderBy('title')->pluck('title', 'id')->all())
                    ->searchable()->required(),
                Select::make('plan_id')->label(__('admin.billing.plan'))
                    ->options(fn (): array => Plan::query()->where('is_active', true)->orderBy('sort')->pluck('name', 'id')->all())
                    ->required()->native(false),
                DateTimePicker::make('starts_at')->label(__('admin.billing.starts_at'))->jalali()->default(now())->required(),
                TextInput::make('trial_days')->label(__('admin.billing.trial_days'))->numeric()->minValue(0)->maxValue(90)->default(0),
                TextInput::make('coupon_code')->label(__('admin.billing.coupon'))
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (filled($value) && ! Coupon::query()->where('code', $value)->first()?->isRedeemable()) {
                            $fail(__('admin.billing.coupon_not_redeemable'));
                        }
                    }),
                Textarea::make('notes')->label(__('admin.billing.notes'))->rows(2),
            ])
            ->action(function (array $data): void {
                try {
                    self::done(app(BillingService::class)->subscribe(
                        Organization::query()->findOrFail($data['organization_id']),
                        Plan::query()->findOrFail($data['plan_id']),
                        startsAt: Carbon::parse($data['starts_at']),
                        trialDays: (int) ($data['trial_days'] ?? 0),
                        coupon: filled($data['coupon_code'] ?? null) ? Coupon::query()->where('code', $data['coupon_code'])->first() : null,
                        notes: $data['notes'] ?? null,
                    ));
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    /** @return iterable<array<string, mixed>> */
    public static function exportRows(): iterable
    {
        foreach (Subscription::query()->with(['organization:id,title', 'plan:id,name'])->lazyById() as $subscription) {
            yield [
                __('filament.models.organization') => $subscription->organization?->title,
                __('admin.billing.plan') => $subscription->plan?->name,
                __('admin.billing.subscription_status') => $subscription->status->label(),
                __('admin.billing.price_per_period').' (ریال)' => $subscription->price_rials,
                __('admin.billing.mrr').' (ریال)' => $subscription->monthlyRevenue(),
                __('admin.billing.starts_at') => JalaliDate::date($subscription->starts_at),
                __('admin.billing.period_ends_at') => JalaliDate::date($subscription->current_period_ends_at),
                __('admin.billing.ended_at') => JalaliDate::date($subscription->ended_at),
            ];
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubscriptions::route('/'),
        ];
    }

    private static function done(mixed $result = null): void
    {
        Notification::make()->title(__('admin.common.saved'))->success()->send();
    }
}
