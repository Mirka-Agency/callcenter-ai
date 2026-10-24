<?php

namespace Tests\Feature;

use App\Domain\Platform\Enums\AdminArea;
use App\Domain\Platform\Enums\BillingPeriod;
use App\Domain\Platform\Enums\CouponType;
use App\Domain\Platform\Enums\InvoiceStatus;
use App\Domain\Platform\Enums\PaymentMethod;
use App\Domain\Platform\Enums\PaymentType;
use App\Domain\Platform\Enums\SubscriptionStatus;
use App\Filament\Pages\Finance\FinanceOverview;
use App\Filament\Pages\Finance\FinanceSettings;
use App\Models\AdminSetting;
use App\Models\Coupon;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\Admin\ProblemInbox;
use App\Services\Billing\BillingMetrics;
use App\Services\Billing\BillingService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformBillingTest extends TestCase
{
    use RefreshDatabase;

    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();

        // 19 Aban 1405; Aban starts 2026-10-23.
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00'));
        $this->billing = app(BillingService::class);
        Money::forgetUnit();
    }

    public function test_subscriptions_drive_mrr_and_mrr_movement(): void
    {
        $monthly = $this->plan(10_000_000);
        $quarterly = $this->plan(27_000_000, BillingPeriod::Quarterly);
        $coupon = Coupon::query()->create(['code' => 'OFF20', 'discount_type' => CouponType::Percent, 'discount_value' => 20, 'is_active' => true]);

        $existing = $this->organization();
        $this->billing->subscribe($existing, $monthly, Carbon::parse('2026-10-01'));

        $new = $this->organization();
        $subscription = $this->billing->subscribe($new, $monthly, Carbon::parse('2026-11-05'), coupon: $coupon);

        $trial = $this->organization();
        $this->billing->subscribe($trial, $quarterly, Carbon::parse('2026-11-01'), trialDays: 14);

        $this->assertSame(8_000_000, $subscription->price_rials);
        $this->assertSame(1, $coupon->fresh()->redemptions);

        $metrics = app(BillingMetrics::class);
        $this->assertSame(18_000_000, $metrics->mrrAt(now()));

        $aban = last($metrics->mrrMovement(2));
        $this->assertSame(10_000_000, $aban['start_mrr']);
        $this->assertSame(8_000_000, $aban['new']);
        $this->assertSame(18_000_000, $aban['end_mrr']);

        $converted = $this->billing->convertTrial($trial->subscriptions()->first());
        $this->assertSame(SubscriptionStatus::Active, $converted->status);
        $this->assertSame(27_000_000, $metrics->mrrAt(now()->addMinute()));

        $this->billing->subscribe($existing, $quarterly);
        $this->assertSame(1, $existing->subscriptions()->current()->count());
        $this->assertSame(SubscriptionStatus::Expired, $existing->subscriptions()->oldest('id')->first()->status);
    }

    public function test_invoice_numbering_vat_payments_and_refunds(): void
    {
        $organization = $this->organization(['legal_name' => 'شرکت نمونه', 'national_id' => '10101010101']);

        $invoice = $this->billing->createInvoice($organization, [
            ['description' => 'اشتراک', 'quantity' => 1, 'unit_price_rials' => 10_000_000],
            ['description' => 'کارشناس اضافه', 'quantity' => 2, 'unit_price_rials' => 1_000_000],
        ], discountRials: 2_000_000, issue: true);

        $this->assertSame('1405-00001', $invoice->number);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame(12_000_000, $invoice->subtotal_rials);
        $this->assertSame(1_000_000, $invoice->tax_rials);
        $this->assertSame(11_000_000, $invoice->total_rials);
        $this->assertSame('شرکت نمونه', $invoice->buyer['name']);
        $this->assertTrue($invoice->due_at->equalTo(now()->addDays(BillingService::DEFAULT_DUE_DAYS)));

        $this->assertSame('1405-00002', $this->billing->createInvoice($organization, [['description' => 'x', 'unit_price_rials' => 1]])->number);

        $this->billing->recordPayment($organization, 5_000_000, PaymentMethod::CardTransfer, $invoice);
        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
        $this->assertSame(6_000_000, $invoice->fresh()->balanceRials());

        $this->billing->recordPayment($organization, 6_000_000, PaymentMethod::BankTransfer, $invoice);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);

        $this->billing->recordPayment($organization, 1_000_000, PaymentMethod::BankTransfer, $invoice, type: PaymentType::Refund);
        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
        $this->assertSame(10_000_000, app(BillingMetrics::class)->cashBetween(now()->subDay(), now()->addDay()));
    }

    public function test_overdue_invoices_flag_subscription_aging_and_problem_inbox(): void
    {
        $organization = $this->organization();
        $subscription = $this->billing->subscribe($organization, $this->plan(10_000_000), now()->subMonth());
        $this->billing->renew($subscription);

        $this->travel(20)->days();

        $this->assertSame(['past_due' => 1, 'expired' => 0], $this->billing->syncStatuses());
        $this->assertSame(SubscriptionStatus::PastDue, $subscription->fresh()->status);

        $aging = app(BillingMetrics::class)->receivablesAging();
        $this->assertSame(1, $aging['0_30']['count']);
        $this->assertSame(11_000_000, $aging['0_30']['amount']);

        $finance = $this->admin([AdminArea::Finance]);
        $support = $this->admin([AdminArea::Support]);
        $this->assertContains('overdue_invoices', array_column(app(ProblemInbox::class)->items($finance), 'key'));
        $this->assertNotContains('overdue_invoices', array_column(app(ProblemInbox::class)->items($support), 'key'));

        $invoice = $subscription->invoices()->first();
        $this->billing->recordPayment($organization, $invoice->total_rials, PaymentMethod::OnlineGateway, $invoice);
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
    }

    public function test_money_display_follows_currency_setting(): void
    {
        $this->assertSame('toman', Money::unit());
        $this->assertSame(1_000_000, Money::toRials(100000));

        AdminSetting::store(AdminSetting::CURRENCY_DISPLAY, Money::RIAL);
        Money::forgetUnit();

        $this->assertSame('ریال', Money::unitLabel());
        $this->assertSame(100000, Money::toRials(100000));
    }

    public function test_finance_pages_render_for_finance_admins_only(): void
    {
        $organization = $this->organization();
        $subscription = $this->billing->subscribe($organization, $this->plan(10_000_000), now()->subDays(3));
        $invoice = $this->billing->renew($subscription);
        $this->billing->recordPayment($organization, 1_000_000, PaymentMethod::Cash, $invoice);

        $finance = $this->admin([AdminArea::Finance]);
        $urls = [
            '/admin/finance-overview',
            '/admin/finance-settings',
            '/admin/plans',
            '/admin/subscriptions',
            '/admin/billing-invoices',
            '/admin/billing-invoices/create',
            '/admin/billing-invoices/'.$invoice->id,
            '/admin/billing-payments',
            '/admin/coupons',
            '/admin/ai-cost-rates',
            '/admin/marketing-spends',
            '/admin/billing-invoices/'.$invoice->id.'/print',
        ];

        foreach ($urls as $url) {
            $this->actingAs($finance)->get($url)->assertOk();
        }

        $this->actingAs($finance)->get('/admin/billing-invoices/'.$invoice->id.'/print')->assertSee($invoice->number);

        $support = $this->admin([AdminArea::Support]);
        $this->actingAs($support)->get('/admin/finance-overview')->assertForbidden();
        $this->actingAs($support)->get('/admin/billing-invoices')->assertForbidden();
        $this->actingAs($support)->get('/admin/billing-invoices/'.$invoice->id.'/print')->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/finance-overview')->assertOk();
    }

    public function test_finance_settings_save_and_overview_renders_data(): void
    {
        $finance = $this->admin([AdminArea::Finance]);
        $this->actingAs($finance);

        Livewire::test(FinanceSettings::class)
            ->set('data.vat_rate', 9)
            ->set('data.invoice_due_days', 14)
            ->set('data.monthly_ai_budget_rials', 5000)
            ->set('data.currency_display', Money::TOMAN)
            ->set('data.seller.name', 'آوایار')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(9, (int) AdminSetting::valueFor(AdminSetting::VAT_RATE));
        $this->assertSame(50000, (int) AdminSetting::valueFor(AdminSetting::MONTHLY_AI_BUDGET_RIALS));
        $this->assertSame('آوایار', AdminSetting::valueFor(AdminSetting::SELLER_PROFILE)['name']);

        $invoice = $this->billing->createInvoice($this->organization(), [['description' => 'x', 'unit_price_rials' => 1_000_000]], issue: true);
        $this->assertSame(90_000, $invoice->tax_rials);
        $this->assertTrue($invoice->due_at->equalTo(now()->addDays(14)));

        Livewire::test(FinanceOverview::class)->assertOk()->assertSee(__('admin.finance.movement_heading'));
    }

    private function plan(int $price, BillingPeriod $period = BillingPeriod::Monthly): Plan
    {
        return Plan::query()->create([
            'name' => 'پلن '.$price,
            'code' => 'plan-'.$price.'-'.$period->value,
            'price_rials' => $price,
            'billing_period' => $period,
            'is_active' => true,
        ]);
    }

    private function organization(array $attributes = []): Organization
    {
        return Organization::factory()->create($attributes);
    }

    /** @param  list<AdminArea>  $areas */
    private function admin(array $areas): User
    {
        return User::factory()->admin()->create(['admin_areas' => array_map(fn (AdminArea $area): string => $area->value, $areas)]);
    }
}
