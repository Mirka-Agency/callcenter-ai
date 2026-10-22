<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_shows_the_hero(): void
    {
        $this->get('http://avayar.org/about')
            ->assertOk()
            ->assertSee('درباره آوایار')
            ->assertSee('ما به صدای مشتری')
            ->assertSee('معنا می‌بخشیم')
            ->assertSee('درخواست مشاوره')
            ->assertSee('images/landing/partners/nova.png', false)
            ->assertDontSee('همراهان مسیر هوشمندسازی')
            ->assertDontSee('همکاری با شرکت‌ها')
            ->assertSee('data-consult-open', false)
            ->assertSee('images/about/voice-orbit.png', false)
            ->assertSee('ساختن آینده‌ای که در آن')
            ->assertSee('هیچ صدایی از دست نمی‌رود')
            ->assertSee('ماموریت ما')
            ->assertSee('کاربران فعال')
            ->assertSee('دقیقه‌های تحلیل‌شده')
            ->assertSee('کسب‌وکارهای در حال استفاده')
            ->assertSee('انسان‌هایی پشت یک هدف بزرگ')
            ->assertSee('فرصت‌های همکاری')
            ->assertSee('images/about/mission-target.png', false)
            ->assertSee('images/about/team-meeting.webp', false)
            ->assertSee('images/about/team-review.webp', false)
            ->assertDontSee('جای تصویر تیم');
    }

    public function test_about_page_on_another_domain_goes_to_login(): void
    {
        $this->get('http://app.example/about')
            ->assertRedirect(route('login'));
    }

    public function test_landing_navigation_links_to_about(): void
    {
        $this->get('http://avayar.org/')
            ->assertOk()
            ->assertSee(route('about'), false)
            ->assertSee('درباره ما');
    }
}
