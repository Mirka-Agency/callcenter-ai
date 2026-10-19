<?php

namespace Tests\Unit;

use App\Models\LandingCalculatorModel;
use App\Services\LandingCostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class LandingCostCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_quote_uses_the_admin_price_per_minute(): void
    {
        $model = $this->rate('Gemini Flash', 640, active: true, default: true);
        $this->rate('Hidden', 9000, active: false, default: false);

        $quote = app(LandingCostCalculator::class)->present();

        $this->assertSame('تومان', $quote['unit']);
        $this->assertSame($model->id, $quote['selectedId']);
        $this->assertCount(1, $quote['models']);
        $this->assertSame(640, $quote['models'][0]['perMinute']);
        $this->assertSame(12_800_000.0, $quote['total']);
    }

    public function test_selects_the_default_model_when_several_are_active(): void
    {
        $this->rate('ارزان', 100, active: true, default: false, position: 1);
        $selected = $this->rate('پیش‌فرض', 250, active: true, default: true, position: 2);

        $quote = app(LandingCostCalculator::class)->present();

        $this->assertSame($selected->id, $quote['selectedId']);
        $this->assertSame(5_000_000.0, $quote['total']);
    }

    public function test_landing_section_renders_the_admin_model(): void
    {
        $model = $this->rate('GPT-4o mini', 480, active: true, default: true);

        $html = Blade::render('<x-landing.cost-calculator />');

        $this->assertStringContainsString('محاسبه هزینه <span class="is-accent">آوایار</span>', $html);
        $this->assertStringContainsString('تعداد تماس‌ها', $html);
        $this->assertStringContainsString('میانگین دقیقه هر تماس', $html);
        $this->assertStringContainsString('مدل هوش مصنوعی', $html);
        $this->assertStringContainsString($model->name, $html);
        $this->assertStringContainsString('data-cost-calculator', $html);
    }

    private function rate(string $name, int $perMinute, bool $active, bool $default, int $position = 0): LandingCalculatorModel
    {
        return LandingCalculatorModel::query()->create([
            'name' => $name,
            'cost_per_minute_tomans' => $perMinute,
            'position' => $position,
            'is_default' => $default,
            'is_active' => $active,
        ]);
    }
}
