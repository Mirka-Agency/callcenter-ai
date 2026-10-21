<?php

namespace Tests\Unit;

use App\Models\LlmModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LlmModelCachedCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_cached_prompt_tokens_are_billed_once_at_the_cached_price(): void
    {
        $model = new LlmModel([
            'input_price_per_million_tokens' => 1,
            'output_price_per_million_tokens' => 4,
            'cached_input_price_per_million_tokens' => 0.1,
        ]);

        $withCache = $model->calculateCost(1000, 0, 800);
        $withoutCache = $model->calculateCost(1000, 0, 0);

        $unit = 500_000;
        $expected = ((200 / 1_000_000) * 1 + (800 / 1_000_000) * 0.1) * $unit;

        $this->assertEqualsWithDelta($expected, $withCache, 0.001);
        $this->assertGreaterThan($withCache, $withoutCache);
    }

    public function test_missing_cached_price_bills_cached_tokens_at_the_input_price(): void
    {
        $model = new LlmModel([
            'input_price_per_million_tokens' => 2,
            'output_price_per_million_tokens' => 4,
        ]);

        $this->assertSame(
            $model->calculateCost(1000, 0, 0),
            $model->calculateCost(1000, 0, 400),
        );
    }
}
