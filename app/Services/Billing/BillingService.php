<?php

namespace App\Services\Billing;

use App\Domain\Platform\Enums\InvoiceStatus;
use App\Domain\Platform\Enums\PaymentMethod;
use App\Domain\Platform\Enums\PaymentType;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Models\AdminSetting;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Coupon;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Admin\AdminAuditLogger;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Morilog\Jalali\Jalalian;

class BillingService
{
    public const DEFAULT_VAT_RATE = 10;

    public const DEFAULT_DUE_DAYS = 7;

    /**
     * Start a subscription. Any current subscription is ended at the same moment,
     * so plan changes keep a clean revenue history.
     */
    public function subscribe(
        Organization $organization,
        Plan $plan,
        ?CarbonInterface $startsAt = null,
        int $trialDays = 0,
        ?Coupon $coupon = null,
        ?string $notes = null,
    ): Subscription {
        if ($coupon && ! $coupon->isRedeemable()) {
            throw new InvalidArgumentException(__('admin.billing.coupon_not_redeemable'));
        }

        $startsAt = Carbon::parse($startsAt ?? now());

        return DB::transaction(function () use ($organization, $plan, $startsAt, $trialDays, $coupon, $notes): Subscription {
            $organization->subscriptions()->current()->get()->each(
                fn (Subscription $current) => $current->update([
                    'status' => SubscriptionStatus::Expired,
                    'ended_at' => $startsAt,
                ]),
            );

            $isTrial = $trialDays > 0;
            $trialEndsAt = $isTrial ? $startsAt->copy()->addDays($trialDays) : null;

            $subscription = $organization->subscriptions()->create([
                'plan_id' => $plan->id,
                'coupon_id' => $coupon?->id,
                'status' => $isTrial ? SubscriptionStatus::Trial : SubscriptionStatus::Active,
                'price_rials' => $coupon ? $coupon->apply($plan->price_rials) : $plan->price_rials,
                'billing_period' => $plan->billing_period,
                'starts_at' => $startsAt,
                'trial_ends_at' => $trialEndsAt,
                'current_period_ends_at' => $trialEndsAt ?? $startsAt->copy()->addMonths($plan->billing_period->months()),
                'notes' => $notes,
            ]);

            $coupon?->increment('redemptions');

            return $subscription;
        });
    }

    /** End a trial and start paying from now on the same plan and price. */
    public function convertTrial(Subscription $trial): Subscription
    {
        if ($trial->status !== SubscriptionStatus::Trial) {
            return $trial;
        }

        return DB::transaction(function () use ($trial): Subscription {
            $now = now();
            $trial->update(['status' => SubscriptionStatus::Expired, 'ended_at' => $now]);

            return $trial->organization->subscriptions()->create([
                'plan_id' => $trial->plan_id,
                'coupon_id' => $trial->coupon_id,
                'status' => SubscriptionStatus::Active,
                'price_rials' => $trial->price_rials,
                'billing_period' => $trial->billing_period,
                'starts_at' => $now,
                'current_period_ends_at' => $now->copy()->addMonths($trial->billing_period->months()),
                'notes' => $trial->notes,
            ]);
        });
    }

    public function cancel(Subscription $subscription, bool $immediately = false): void
    {
        $endsAt = $immediately || $subscription->status === SubscriptionStatus::Trial
            ? now()
            : ($subscription->current_period_ends_at ?? now());

        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'ended_at' => $endsAt,
        ]);
    }

    /** Extend the subscription by one period and issue the invoice for it. */
    public function renew(Subscription $subscription): BillingInvoice
    {
        $periodStart = $subscription->current_period_ends_at ?? now();
        $periodEnd = $periodStart->copy()->addMonths($subscription->billing_period->months());

        $invoice = $this->invoiceForPeriod($subscription, $periodStart, $periodEnd);

        $subscription->update([
            'current_period_ends_at' => $periodEnd,
            'status' => $subscription->status === SubscriptionStatus::Trial ? SubscriptionStatus::Trial : SubscriptionStatus::Active,
        ]);

        return $invoice;
    }

    public function invoiceForPeriod(Subscription $subscription, CarbonInterface $from, CarbonInterface $to): BillingInvoice
    {
        $plan = $subscription->plan;

        $description = __('admin.billing.invoice_line_subscription', [
            'plan' => $plan->name,
            'from' => Jalalian::fromCarbon(Carbon::parse($from))->format('Y/m/d'),
            'to' => Jalalian::fromCarbon(Carbon::parse($to))->format('Y/m/d'),
        ]);

        return $this->createInvoice(
            $subscription->organization,
            [['description' => $description, 'quantity' => 1, 'unit_price_rials' => $plan->price_rials]],
            discountRials: max(0, $plan->price_rials - $subscription->price_rials),
            subscription: $subscription,
            issue: true,
        );
    }

    /**
     * @param  list<array{description: string, quantity?: int, unit_price_rials: int}>  $lines
     */
    public function createInvoice(
        Organization $organization,
        array $lines,
        int $discountRials = 0,
        ?Subscription $subscription = null,
        bool $issue = false,
        ?string $notes = null,
    ): BillingInvoice {
        return DB::transaction(function () use ($organization, $lines, $discountRials, $subscription, $issue, $notes): BillingInvoice {
            $invoice = $this->createWithUniqueNumber($organization, $subscription, $notes);

            foreach ($lines as $line) {
                $quantity = max(1, (int) ($line['quantity'] ?? 1));
                $invoice->lines()->create([
                    'description' => $line['description'],
                    'quantity' => $quantity,
                    'unit_price_rials' => (int) $line['unit_price_rials'],
                    'amount_rials' => $quantity * (int) $line['unit_price_rials'],
                ]);
            }

            $this->recalculate($invoice, $discountRials);

            if ($issue) {
                $this->issue($invoice);
            }

            return $invoice->refresh();
        });
    }

    public function recalculate(BillingInvoice $invoice, ?int $discountRials = null): void
    {
        $subtotal = (int) $invoice->lines()->sum('amount_rials');
        $discount = min($subtotal, $discountRials ?? $invoice->discount_rials);
        $taxRate = (float) $invoice->tax_rate;
        $tax = (int) round(($subtotal - $discount) * $taxRate / 100);

        $invoice->update([
            'subtotal_rials' => $subtotal,
            'discount_rials' => $discount,
            'tax_rials' => $tax,
            'total_rials' => $subtotal - $discount + $tax,
        ]);
    }

    public function issue(BillingInvoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return;
        }

        $dueDays = (int) AdminSetting::valueFor(AdminSetting::INVOICE_DUE_DAYS, self::DEFAULT_DUE_DAYS);

        $invoice->update([
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
            'due_at' => now()->addDays($dueDays),
            'buyer' => $this->buyerSnapshot($invoice->organization),
        ]);

        app(AdminAuditLogger::class)->log('invoice_issued', $invoice);
    }

    public function void(BillingInvoice $invoice): void
    {
        $invoice->update(['status' => InvoiceStatus::Void]);
    }

    public function recordPayment(
        Organization $organization,
        int $amountRials,
        PaymentMethod $method,
        ?BillingInvoice $invoice = null,
        ?CarbonInterface $paidAt = null,
        ?string $reference = null,
        PaymentType $type = PaymentType::Payment,
        ?string $notes = null,
    ): BillingPayment {
        if ($amountRials <= 0) {
            throw new InvalidArgumentException(__('admin.billing.amount_must_be_positive'));
        }

        return DB::transaction(function () use ($organization, $amountRials, $method, $invoice, $paidAt, $reference, $type, $notes): BillingPayment {
            $payment = $organization->billingPayments()->create([
                'billing_invoice_id' => $invoice?->id,
                'type' => $type,
                'amount_rials' => $amountRials,
                'paid_at' => $paidAt ?? now(),
                'method' => $method,
                'reference' => $reference,
                'notes' => $notes,
                'recorded_by_user_id' => auth()->id(),
            ]);

            if ($invoice) {
                $invoice->refreshPaymentStatus();

                if ($invoice->status === InvoiceStatus::Paid && $invoice->subscription?->status === SubscriptionStatus::PastDue) {
                    $invoice->subscription->update(['status' => SubscriptionStatus::Active]);
                }
            }

            return $payment;
        });
    }

    /**
     * Daily housekeeping: flag subscriptions with overdue invoices, and close
     * trials and cancelled subscriptions whose end date passed.
     *
     * @return array{past_due: int, expired: int}
     */
    public function syncStatuses(): array
    {
        $pastDue = Subscription::query()
            ->where('status', SubscriptionStatus::Active->value)
            ->whereHas('invoices', fn ($query) => $query->overdue())
            ->update(['status' => SubscriptionStatus::PastDue->value]);

        $expiredTrials = Subscription::query()
            ->where('status', SubscriptionStatus::Trial->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->update(['status' => SubscriptionStatus::Expired->value, 'ended_at' => DB::raw('trial_ends_at')]);

        return ['past_due' => $pastDue, 'expired' => $expiredTrials];
    }

    /** @return array<string, string|null> */
    public function buyerSnapshot(Organization $organization): array
    {
        return [
            'name' => $organization->legal_name ?: $organization->title,
            'national_id' => $organization->national_id,
            'economic_code' => $organization->economic_code,
            'address' => $organization->billing_address,
            'postal_code' => $organization->postal_code,
            'phone' => $organization->billing_phone ?: $organization->employer?->phone,
        ];
    }

    private function createWithUniqueNumber(Organization $organization, ?Subscription $subscription, ?string $notes): BillingInvoice
    {
        $year = Jalalian::now()->format('Y');
        $taxRate = (float) AdminSetting::valueFor(AdminSetting::VAT_RATE, self::DEFAULT_VAT_RATE);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $sequence = BillingInvoice::query()->where('number', 'like', $year.'-%')->count() + 1 + $attempt;

            try {
                return DB::transaction(fn (): BillingInvoice => $organization->billingInvoices()->create([
                    'subscription_id' => $subscription?->id,
                    'number' => sprintf('%s-%05d', $year, $sequence),
                    'status' => InvoiceStatus::Draft,
                    'tax_rate' => $taxRate,
                    'notes' => $notes,
                ]));
            } catch (QueryException $exception) {
                if ($attempt === 4) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Could not allocate an invoice number.');
    }
}
