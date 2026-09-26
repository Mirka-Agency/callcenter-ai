<?php

namespace Tests\Unit;

use App\Support\MetricTone;
use PHPUnit\Framework\TestCase;

class MetricToneTest extends TestCase
{
    public function test_maps_score_bands_to_dashboard_tones(): void
    {
        $this->assertSame('good', MetricTone::fromScore(70));
        $this->assertSame('good', MetricTone::fromScore(92.5));
        $this->assertSame('medium', MetricTone::fromScore(69.9));
        $this->assertSame('medium', MetricTone::fromScore(40));
        $this->assertSame('bad', MetricTone::fromScore(39.9));
        $this->assertSame('bad', MetricTone::fromScore(20));
    }

    public function test_empty_scores_stay_neutral(): void
    {
        $this->assertNull(MetricTone::fromScore(null));
        $this->assertNull(MetricTone::fromScore(0));
        $this->assertNull(MetricTone::fromScore(''));
    }
}
