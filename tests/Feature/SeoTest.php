<?php

namespace Tests\Feature;

use App\Application\Blog\Actions\FindOrCreateRecoverableDraft;
use App\Application\Blog\Actions\SaveBlogCategory;
use App\Application\Blog\Actions\SaveBlogPost;
use App\Application\Blog\Support\BlogPostEditLock;
use App\Domain\Blog\BlogPostStatus;
use App\Domain\Platform\Enums\AdminArea;
use App\Domain\Seo\RedirectStatus;
use App\Filament\Pages\Seo\SeoSettingsPage;
use App\Filament\Pages\Seo\StaticPagesSeo;
use App\Filament\Resources\SeoRedirects\Pages\ManageSeoRedirects;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\SeoRedirect;
use App\Models\User;
use App\Support\Seo\RedirectResolver;
use App\Support\Seo\SeoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://avayar.org';

    public function test_exact_and_prefix_redirects_keep_the_query_string_and_count_hits(): void
    {
        $exact = SeoRedirect::query()->create(['source_path' => 'https://avayar.org/Old-Page/', 'target_url' => '/about', 'status_code' => 301]);
        SeoRedirect::query()->create(['source_path' => '/docs', 'match_type' => SeoRedirect::PREFIX, 'target_url' => '/blog/*', 'status_code' => 302]);

        $this->assertSame('/old-page', $exact->source_path);

        $this->get(self::HOST.'/old-page?utm_source=x')->assertStatus(301)->assertRedirect(self::HOST.'/about?utm_source=x');
        $this->get(self::HOST.'/docs/setup/voip')->assertStatus(302)->assertRedirect(self::HOST.'/blog/setup/voip');
        $this->post(self::HOST.'/old-page')->assertNotFound();

        $this->assertSame(1, $exact->fresh()->hits);
        $this->assertNotNull($exact->fresh()->last_hit_at);
    }

    public function test_gone_rule_returns_410_and_inactive_rules_are_ignored(): void
    {
        SeoRedirect::query()->create(['source_path' => '/removed', 'target_url' => '/ignored', 'status_code' => RedirectStatus::Gone]);
        SeoRedirect::query()->create(['source_path' => '/paused', 'target_url' => '/about', 'status_code' => 301, 'is_active' => false]);

        $this->get(self::HOST.'/removed')
            ->assertStatus(410)
            ->assertSee('noindex, follow', false)
            ->assertSee(__('seo.gone.title'));
        $this->assertNull(SeoRedirect::query()->where('source_path', '/removed')->value('target_url'));

        $this->get(self::HOST.'/paused')->assertNotFound();
    }

    public function test_loop_guard_rejects_redirect_cycles(): void
    {
        SeoRedirect::query()->create(['source_path' => '/a', 'target_url' => '/b', 'status_code' => 301]);
        SeoRedirect::query()->create(['source_path' => '/b', 'target_url' => '/c', 'status_code' => 301]);

        $this->expectException(ValidationException::class);

        app(RedirectResolver::class)->assertNoLoop('/c', SeoRedirect::EXACT, self::HOST.'/a');
    }

    public function test_changing_a_published_slug_adds_a_301_and_flattens_older_redirects(): void
    {
        $category = BlogCategory::query()->create(['name' => 'آموزش', 'slug' => 'learn']);
        $post = BlogPost::factory()->create([
            'slug' => 'first',
            'category_id' => $category->id,
            'status' => BlogPostStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        $post->update(['slug' => 'second']);
        $post->update(['slug' => 'third']);

        $this->get(self::HOST.'/blog/first')->assertStatus(301)->assertRedirect(self::HOST.'/blog/third');
        $this->get(self::HOST.'/blog/second')->assertStatus(301)->assertRedirect(self::HOST.'/blog/third');

        $post->update(['slug' => 'first']);

        $this->assertFalse(SeoRedirect::query()->where('source_path', '/blog/first')->exists());
        $this->get(self::HOST.'/blog/first')->assertOk();

        $category->update(['slug' => 'guides']);
        $this->get(self::HOST.'/blog/category/learn')->assertStatus(301)->assertRedirect(self::HOST.'/blog/category/guides');
    }

    public function test_draft_slug_changes_do_not_create_redirects(): void
    {
        $post = BlogPost::factory()->create(['slug' => 'draft-one', 'status' => BlogPostStatus::Draft, 'published_at' => null]);

        $post->update(['slug' => 'draft-two']);

        $this->assertSame(0, SeoRedirect::query()->count());
    }

    public function test_robots_txt_uses_custom_content_on_landing_and_blocks_the_app_host(): void
    {
        $this->get(self::HOST.'/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin')
            ->assertSee('Sitemap: ');

        SeoSettings::store([...SeoSettings::all(), 'robots_txt' => "User-agent: *\r\nDisallow: /private"]);

        $this->assertSame("User-agent: *\nDisallow: /private\n", $this->get(self::HOST.'/robots.txt')->getContent());

        config()->set('app.env', 'production');
        $this->get('http://app.example/robots.txt')->assertOk()->assertSee('Disallow: /');
        $this->assertStringNotContainsString('private', $this->get('http://app.example/robots.txt')->getContent());
    }

    public function test_sitemap_index_and_children_list_only_indexable_content(): void
    {
        $category = BlogCategory::query()->create(['name' => 'آموزش', 'slug' => 'learn']);
        $hidden = BlogCategory::query()->create(['name' => 'پنهان', 'slug' => 'hidden', 'noindex' => true]);
        BlogCategory::query()->create(['name' => 'خالی', 'slug' => 'empty']);

        BlogPost::factory()->create(['slug' => 'live-post', 'category_id' => $category->id, 'status' => BlogPostStatus::Published, 'published_at' => now()->subDay(), 'cover_image_url' => '/storage/blog/cover.png']);
        BlogPost::factory()->create(['slug' => 'noindex-post', 'category_id' => $hidden->id, 'status' => BlogPostStatus::Published, 'published_at' => now()->subDay(), 'noindex' => true]);
        BlogPost::factory()->create(['slug' => 'future-post', 'category_id' => $category->id, 'status' => BlogPostStatus::Published, 'published_at' => now()->addDay()]);
        BlogPost::factory()->create(['slug' => 'draft-post', 'category_id' => $category->id, 'status' => BlogPostStatus::Draft, 'published_at' => null]);

        $this->get(self::HOST.'/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<sitemapindex', false)
            ->assertSee(route('sitemap.child', 'pages'), false)
            ->assertSee(route('sitemap.child', 'categories'), false)
            ->assertSee(route('sitemap.child', 'posts'), false);

        $this->get(self::HOST.'/sitemaps/pages.xml')->assertOk()->assertSee(route('about'), false)->assertSee(route('blog'), false);

        $this->get(self::HOST.'/sitemaps/categories.xml')
            ->assertOk()
            ->assertSee(route('blog.category', 'learn'), false)
            ->assertDontSee('/blog/category/hidden', false)
            ->assertDontSee('/blog/category/empty', false);

        $this->get(self::HOST.'/sitemaps/posts.xml')
            ->assertOk()
            ->assertSee(route('blog.show', 'live-post'), false)
            ->assertSee('<image:loc>'.url('/storage/blog/cover.png').'</image:loc>', false)
            ->assertDontSee('noindex-post')
            ->assertDontSee('future-post')
            ->assertDontSee('draft-post');

        $this->get(self::HOST.'/sitemaps/unknown.xml')->assertNotFound();
    }

    public function test_post_page_renders_meta_social_tags_and_structured_data_with_title_fallback(): void
    {
        $category = BlogCategory::query()->create(['name' => 'آموزش', 'slug' => 'learn']);
        $post = BlogPost::factory()->create([
            'title' => 'عنوان اصلی مقاله',
            'slug' => 'seo-post',
            'excerpt' => 'خلاصه مقاله',
            'category_id' => $category->id,
            'status' => BlogPostStatus::Published,
            'published_at' => now()->subDay(),
            'meta_title' => null,
            'meta_description' => null,
            'og_image_url' => '/storage/blog/og.png',
            'faq_json' => [['question' => 'سؤال اول؟', 'answer' => 'پاسخ اول']],
        ]);

        $this->get(self::HOST.'/blog/seo-post')
            ->assertOk()
            ->assertSee('<title>عنوان اصلی مقاله', false)
            ->assertSee('<meta name="description" content="خلاصه مقاله"', false)
            ->assertSee('<link rel="canonical" href="'.route('blog.show', 'seo-post').'"', false)
            ->assertSee('<meta property="og:type" content="article"', false)
            ->assertSee('<meta property="og:image" content="'.url('/storage/blog/og.png').'"', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image"', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);

        $post->update(['meta_title' => 'عنوان سئو', 'noindex' => true]);

        $this->get(self::HOST.'/blog/seo-post')
            ->assertSee('<title>عنوان سئو', false)
            ->assertSee('<meta name="robots" content="noindex, follow"', false);
    }

    public function test_category_page_uses_its_own_meta_and_faq_and_search_results_are_noindex(): void
    {
        $category = BlogCategory::query()->create([
            'name' => 'آموزش',
            'slug' => 'learn',
            'description' => '<p>توضیح دسته</p>',
            'meta_description' => 'توضیحات سئو دسته',
            'faq_json' => [['question' => 'دسته چیست؟', 'answer' => 'پاسخ دسته']],
        ]);
        BlogPost::factory()->create(['slug' => 'in-learn', 'category_id' => $category->id, 'status' => BlogPostStatus::Published, 'published_at' => now()->subDay()]);

        $this->get(self::HOST.'/blog/category/learn')
            ->assertOk()
            ->assertSee('<title>آموزش', false)
            ->assertSee('<meta name="description" content="توضیحات سئو دسته"', false)
            ->assertSee('دسته چیست؟')
            ->assertSee('"@type":"CollectionPage"', false)
            ->assertSee('index, follow', false);

        $this->get(self::HOST.'/blog/category/learn?q=test')->assertSee('noindex, follow', false);
        $this->get(self::HOST.'/blog/category/missing')->assertNotFound();
    }

    public function test_static_page_meta_comes_from_settings_and_falls_back_to_defaults(): void
    {
        $settings = SeoSettings::all();
        $settings['pages']['about']['title'] = 'درباره ما در گوگل';
        $settings['pages']['about']['description'] = '';
        SeoSettings::store($settings);

        $this->get(self::HOST.'/about')
            ->assertOk()
            ->assertSee('<title>درباره ما در گوگل</title>', false)
            ->assertSee(SeoSettings::defaults()['pages']['about']['description']);
    }

    public function test_writers_can_save_category_and_post_seo_fields(): void
    {
        $writer = User::factory()->writer()->create();
        $this->actingAs($writer);

        $category = app(SaveBlogCategory::class)->handle($writer, null, [
            'name' => 'آموزش',
            'slug' => 'learn',
            'meta_title' => ' عنوان دسته ',
            'meta_description' => 'توضیح',
            'noindex' => true,
            'faq_json' => [['question' => 'سؤال؟', 'answer' => 'پاسخ']],
        ]);

        $this->assertSame('عنوان دسته', $category->meta_title);
        $this->assertTrue($category->noindex);
        $this->assertSame([['question' => 'سؤال؟', 'answer' => 'پاسخ']], $category->faq_json);

        $post = app(FindOrCreateRecoverableDraft::class)->handle($writer);
        app(BlogPostEditLock::class)->acquire((int) $post->id, $writer);

        $saved = app(SaveBlogPost::class)->handle($writer, $post, [
            'title' => 'مطلب',
            'slug' => 'writer-seo',
            'body' => '<p>متن</p>',
            'noindex' => true,
            'author_user_id' => $writer->id,
            'content_version' => $post->content_version,
        ]);

        $this->assertTrue($saved->noindex);

        $this->get('/admin/blog-categories/'.$category->id.'/edit')->assertOk()->assertSee(__('seo.fields.preview'));
    }

    public function test_only_content_admins_manage_redirects_and_seo_settings(): void
    {
        $this->actingAs(User::factory()->writer()->create());
        $this->get('/admin/seo-redirects')->assertForbidden();
        $this->get('/admin/seo-settings')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create(['admin_areas' => [AdminArea::Finance->value]]));
        $this->get('/admin/seo-redirects')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create(['admin_areas' => [AdminArea::Content->value]]));
        $this->get('/admin/seo-redirects')->assertOk();
        $this->get('/admin/seo-settings')->assertOk()->assertSee(__('seo.settings.reset_robots'));
    }

    public function test_admin_creates_redirects_with_loop_and_duplicate_validation(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        SeoRedirect::query()->create(['source_path' => '/b', 'target_url' => '/a', 'status_code' => 301]);
        SeoRedirect::query()->create(['source_path' => '/old', 'target_url' => '/b', 'status_code' => 301]);

        Livewire::test(ManageSeoRedirects::class)
            ->callAction('create', ['source_path' => '/a', 'match_type' => 'exact', 'status_code' => 301, 'target_url' => '/b', 'is_active' => true])
            ->assertHasFormErrors(['target_url']);

        Livewire::test(ManageSeoRedirects::class)
            ->callAction('create', ['source_path' => '/B/', 'match_type' => 'exact', 'status_code' => 301, 'target_url' => '/c', 'is_active' => true])
            ->assertHasFormErrors(['source_path']);

        Livewire::test(ManageSeoRedirects::class)
            ->callAction('create', ['source_path' => '/a', 'match_type' => 'exact', 'status_code' => 410, 'is_active' => true])
            ->assertHasNoFormErrors();

        $gone = SeoRedirect::query()->where('source_path', '/a')->firstOrFail();
        $this->assertSame(RedirectStatus::Gone, $gone->status_code);
        $this->assertSame($admin->id, $gone->created_by_user_id);

        Livewire::test(ManageSeoRedirects::class)
            ->callAction('create', ['source_path' => '/b2', 'match_type' => 'exact', 'status_code' => 301, 'target_url' => '/final', 'is_active' => true])
            ->assertHasNoFormErrors();
        SeoRedirect::query()->where('source_path', '/old')->update(['target_url' => '/b2']);

        Livewire::test(ManageSeoRedirects::class)
            ->callAction('create', ['source_path' => '/final', 'match_type' => 'exact', 'status_code' => 301, 'target_url' => '/really-final', 'is_active' => true])
            ->assertHasNoFormErrors();

        $this->assertSame('/really-final', SeoRedirect::query()->where('source_path', '/b2')->value('target_url'));
    }

    public function test_settings_page_saves_robots_and_social_profile(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(SeoSettingsPage::class)
            ->fillForm([
                'twitter_site' => 'avayar',
                'blog_title_suffix' => '',
                'same_as' => ['https://www.linkedin.com/company/avayar'],
                'robots_txt' => "User-agent: *\nDisallow: /tmp",
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame("User-agent: *\nDisallow: /tmp\n", SeoSettings::robotsTxt());
        $this->assertSame('', SeoSettings::all()['blog_title_suffix']);
        $this->assertSame(['https://www.linkedin.com/company/avayar'], SeoSettings::get('same_as'));

        Livewire::test(SeoSettingsPage::class)
            ->fillForm(['robots_txt' => SeoSettings::defaultRobotsTxt()])
            ->call('save');

        $this->assertNull(SeoSettings::all()['robots_txt']);
        $this->get(self::HOST.'/')->assertSee('"sameAs":["https://www.linkedin.com/company/avayar"]', false)
            ->assertSee('<meta name="twitter:site" content="@avayar"', false);
    }

    public function test_static_pages_seo_page_saves_meta_with_fallback_to_defaults(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $this->get('/admin/seo-static-pages')->assertOk()->assertSee(__('seo.settings.pages.contact'));

        Livewire::test(StaticPagesSeo::class)
            ->assertFormSet(['pages.about.title' => null])
            ->fillForm([
                'pages.about.title' => 'درباره تیم آوایار',
                'pages.about.og_title' => 'آوایار را بشناسید',
                'pages.contact.noindex' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get(self::HOST.'/about')
            ->assertSee('<title>درباره تیم آوایار</title>', false)
            ->assertSee('<meta property="og:title" content="آوایار را بشناسید"', false)
            ->assertSee(SeoSettings::defaults()['pages']['about']['description']);
        $this->get(self::HOST.'/contact')->assertSee('<meta name="robots" content="noindex, follow"', false);
        $this->get(self::HOST.'/sitemaps/pages.xml')->assertSee(route('about'), false)->assertDontSee(route('contact'), false);

        Livewire::test(StaticPagesSeo::class)
            ->fillForm(['pages.about.title' => ''])
            ->call('save');

        $this->get(self::HOST.'/about')->assertSee('<title>'.e(SeoSettings::defaults()['pages']['about']['title']).'</title>', false);
        Livewire::test(SeoSettingsPage::class)->fillForm(['twitter_site' => 'avayar'])->call('save')->assertHasNoFormErrors();

        $this->assertSame('آوایار را بشناسید', SeoSettings::get('pages.about.og_title'));
        $this->assertTrue(SeoSettings::get('pages.contact.noindex'));
    }

    public function test_writers_cannot_open_static_pages_seo(): void
    {
        $this->actingAs(User::factory()->writer()->create());
        $this->get('/admin/seo-static-pages')->assertForbidden();
    }
}
