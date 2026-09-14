<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_groups_are_collapsed_by_default(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        $groups = collect($panel->getNavigation())
            ->filter(fn (NavigationGroup $group): bool => filled($group->getLabel()));

        $this->assertNotEmpty($groups);

        foreach ($groups as $group) {
            $this->assertTrue($group->isCollapsed(), $group->getLabel());
        }
    }
}
