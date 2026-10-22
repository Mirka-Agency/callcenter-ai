<?php

namespace Tests\Feature;

use App\Domain\Blog\BlogPostStatus;
use App\Models\BlogPost;
use App\Support\Blog\BlogCatalog;
use App\Support\PersianNumber;
use Database\Seeders\BlogCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BlogCatalogSeeder::class);
    }

    public function test_blog_page_shows_the_catalog_layout(): void
    {
        $counts = collect(BlogCatalog::seedData()['posts'])->countBy('category')->all();

        $this->assertSame(24, array_sum($counts));
        $this->assertSame(6, $counts['ai']);
        $this->assertSame(5, $counts['call-center']);
        $this->assertSame(4, $counts['sales']);
        $this->assertSame(4, $counts['customer']);
        $this->assertSame(3, $counts['leadership']);
        $this->assertSame(2, $counts['guide']);

        $this->get('http://avayar.org/blog')
            ->assertOk()
            ->assertSee('وبلاگ آوایار')
            ->assertSee('مقالات، راهنماها و تجربیات کاربردی')
            ->assertSee('دسته‌بندی‌ها')
            ->assertSee('همه مطالب')
            ->assertSee('هوش مصنوعی')
            ->assertSee('مرکز تماس')
            ->assertDontSee('عضویت در خبرنامه')
            ->assertSee('جستجوی مقالات، موضوعات ...')
            ->assertSee('چگونه کیفیت مکالمات مرکز تماس را با هوش مصنوعی بهبود دهیم؟')
            ->assertSee('هوش مصنوعی در خدمت رشد کسب‌وکارها')
            ->assertSee('راهنمای شروع کار با آوایار')
            ->assertSee('نقش رهبری در موفقیت تیم فروش')
            ->assertSee('مطالعه بیشتر')
            ->assertSee('images/blog/hero-notes.png', false)
            ->assertDontSee('blog-subscriptions', false)
            ->assertSee(PersianNumber::format(array_sum($counts)))
            ->assertSee('aria-current="page"', false);
    }

    public function test_blog_page_on_another_domain_goes_to_login(): void
    {
        $this->get('http://app.example/blog')
            ->assertRedirect(route('login'));
    }

    public function test_landing_navigation_links_to_the_blog(): void
    {
        $this->get('http://avayar.org/')
            ->assertOk()
            ->assertSee(route('blog'), false)
            ->assertSee('وبلاگ');
    }

    public function test_category_filter_limits_the_visible_articles(): void
    {
        $this->get('http://avayar.org/blog?category=guide')
            ->assertOk()
            ->assertSee('راهنمای شروع کار با آوایار')
            ->assertSee('جستجو بین مکالمه‌های تحلیل‌شده')
            ->assertDontSee('نقش رهبری در موفقیت تیم فروش');
    }

    public function test_search_finds_an_article_and_reports_an_empty_result(): void
    {
        $this->get('http://avayar.org/blog?q='.urlencode('شروع کار'))
            ->assertOk()
            ->assertSee('راهنمای شروع کار با آوایار')
            ->assertDontSee('نقش رهبری در موفقیت تیم فروش');

        $this->get('http://avayar.org/blog?q='.urlencode('عبارتی که در هیچ مقاله‌ای نیست'))
            ->assertOk()
            ->assertSee('مقاله‌ای پیدا نشد');
    }

    public function test_second_page_hides_the_newest_article(): void
    {
        $this->get('http://avayar.org/blog?page=2')
            ->assertOk()
            ->assertSee('خلاصه‌سازی خودکار تماس چه کمکی به مدیر می‌کند؟')
            ->assertDontSee('چگونه کیفیت مکالمات مرکز تماس را با هوش مصنوعی بهبود دهیم؟');
    }

    public function test_article_page_shows_the_body_and_unknown_slug_is_missing(): void
    {
        $this->get('http://avayar.org/blog/getting-started-with-avayar')
            ->assertOk()
            ->assertSee('راهنمای شروع کار با آوایار')
            ->assertSee('آوایار تماس پایان‌یافته را تحلیل می‌کند')
            ->assertSee('مقاله‌های مرتبط')
            ->assertSee(route('blog'), false);

        $this->get('http://avayar.org/blog/missing-article')
            ->assertNotFound();
    }

    public function test_draft_articles_are_not_public(): void
    {
        BlogPost::factory()->create([
            'slug' => 'secret-draft',
            'title' => 'پیش‌نویس مخفی',
            'status' => BlogPostStatus::Draft,
            'published_at' => null,
        ]);

        $this->get('http://avayar.org/blog/secret-draft')
            ->assertNotFound();

        $this->get('http://avayar.org/blog')
            ->assertDontSee('پیش‌نویس مخفی');
    }

    public function test_article_page_on_another_domain_goes_to_login(): void
    {
        $this->get('http://app.example/blog/getting-started-with-avayar')
            ->assertRedirect(route('login'));
    }
}
