<?php

namespace Tests\Unit;

use App\Enums\ReportDatePreset;
use Tests\TestCase;

class ReportDatePresetTest extends TestCase
{
    public function test_named_presets_follow_filter_button_order_and_exclude_custom(): void
    {
        $this->assertSame([
            ReportDatePreset::Today,
            ReportDatePreset::Yesterday,
            ReportDatePreset::Last7,
            ReportDatePreset::Last30,
            ReportDatePreset::ThisMonth,
            ReportDatePreset::PreviousMonth,
            ReportDatePreset::CurrentQuarter,
            ReportDatePreset::CurrentYear,
        ], ReportDatePreset::namedPresets());
    }
}
