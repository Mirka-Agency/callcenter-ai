<?php

namespace Tests\Unit;

use App\Support\OnPrem;
use Tests\TestCase;

class OnPremTest extends TestCase
{
    public function test_billing_is_visible_by_default(): void
    {
        config(['onprem.enabled' => false]);

        $this->assertFalse(OnPrem::enabled());
        $this->assertFalse(OnPrem::billingHidden());
        $this->assertSame(__('filament.navigation.groups.ai_billing'), OnPrem::llmNavigationGroup());
    }

    public function test_billing_is_hidden_when_onprem_is_enabled(): void
    {
        config(['onprem.enabled' => true]);

        $this->assertTrue(OnPrem::enabled());
        $this->assertTrue(OnPrem::billingHidden());
        $this->assertSame(__('filament.navigation.groups.ai_management'), OnPrem::llmNavigationGroup());
    }
}
