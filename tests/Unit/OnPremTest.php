<?php

namespace Tests\Unit;

use App\Support\OnPrem;
use Tests\TestCase;

class OnPremTest extends TestCase
{
    public function test_onprem_is_disabled_by_default(): void
    {
        config(['onprem.enabled' => false]);

        $this->assertFalse(OnPrem::enabled());
    }

    public function test_onprem_follows_config(): void
    {
        config(['onprem.enabled' => true]);

        $this->assertTrue(OnPrem::enabled());
    }
}
