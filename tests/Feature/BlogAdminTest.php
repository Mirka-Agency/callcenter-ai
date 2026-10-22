<?php

namespace Tests\Feature;

use App\Application\Blog\Actions\AutosaveBlogPost;
use App\Application\Blog\Actions\DeleteBlogCategory;
use App\Application\Blog\Actions\DiscardRecoverableDraft;
use App\Application\Blog\Actions\FindOrCreateRecoverableDraft;
use App\Application\Blog\Actions\SaveBlogPost;
use App\Application\Blog\Support\BlogPostEditLock;
use App\Application\Blog\Support\HtmlSanitizer;
use App\Domain\Blog\BlogPostStatus;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Resources\BlogCategories\Pages\CreateBlogCategory;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BlogAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_blog_post_list(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListBlogPosts::class)
            ->assertSuccessful()
            ->assertSee('مطالب وبلاگ');
    }

    public function test_platform_admin_cannot_access_blog_resources(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse(BlogPostResource::canViewAny());
        $this->assertFalse(BlogCategoryResource::canViewAny());
    }

    public function test_employer_cannot_access_blog_admin(): void
    {
        $this->actingAs(User::factory()->employer()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse(BlogPostResource::canViewAny());
    }

    public function test_create_reuses_recoverable_draft_and_discard_creates_fresh(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $first = app(FindOrCreateRecoverableDraft::class)->handle($user);
        $second = app(FindOrCreateRecoverableDraft::class)->handle($user);

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($first->is_recoverable_draft);

        app(DiscardRecoverableDraft::class)->handle($user, $first);

        $this->assertDatabaseMissing('blog_posts', ['id' => $first->id]);

        $third = app(FindOrCreateRecoverableDraft::class)->handle($user);
        $this->assertNotSame($first->id, $third->id);
    }

    public function test_create_page_redirects_to_edit_draft(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateBlogPost::class)
            ->assertRedirect();

        $draft = BlogPost::query()->where('owned_by_user_id', $user->id)->first();
        $this->assertNotNull($draft);
        $this->assertTrue($draft->is_recoverable_draft);
    }

    public function test_publish_requires_title_and_sets_published_at(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $post = app(FindOrCreateRecoverableDraft::class)->handle($user);
        app(BlogPostEditLock::class)->acquire((int) $post->id, $user);

        try {
            app(SaveBlogPost::class)->handle($user, $post, [
                'title' => '',
                'body' => '<p>متن</p>',
                'publish' => true,
                'content_version' => $post->content_version,
            ]);
            $this->fail('Expected validation exception');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('title', $exception->errors());
        }

        $saved = app(SaveBlogPost::class)->handle($user, $post->fresh(), [
            'title' => 'عنوان منتشرشده',
            'slug' => 'published-title',
            'body' => '<p>متن امن</p><script>alert(1)</script>',
            'publish' => true,
            'content_version' => $post->fresh()->content_version,
        ]);

        $this->assertSame(BlogPostStatus::Published, $saved->status);
        $this->assertNotNull($saved->published_at);
        $this->assertFalse($saved->is_recoverable_draft);
        $this->assertStringNotContainsString('<script>', $saved->body);
        $this->assertStringContainsString('<p>', $saved->body);
    }

    public function test_autosave_updates_draft_without_publishing(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $post = app(FindOrCreateRecoverableDraft::class)->handle($user);
        app(BlogPostEditLock::class)->acquire((int) $post->id, $user);

        $saved = app(AutosaveBlogPost::class)->handle($user, $post, [
            'title' => 'پیش‌نویس ذخیره خودکار',
            'body' => '<p>محتوای موقت</p>',
            'content_version' => $post->content_version,
        ]);

        $this->assertSame(BlogPostStatus::Draft, $saved->status);
        $this->assertNull($saved->published_at);
        $this->assertSame('پیش‌نویس ذخیره خودکار', $saved->title);
    }

    public function test_autosave_rejects_published_posts(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $post = BlogPost::factory()->published()->create([
            'owned_by_user_id' => $user->id,
        ]);
        app(BlogPostEditLock::class)->acquire((int) $post->id, $user);

        $this->expectException(ValidationException::class);

        app(AutosaveBlogPost::class)->handle($user, $post, [
            'title' => $post->title,
            'body' => $post->body,
            'content_version' => $post->content_version,
        ]);
    }

    public function test_lock_blocks_other_editor_mutations(): void
    {
        $owner = User::factory()->superAdmin()->create(['name' => 'Owner']);
        $other = User::factory()->superAdmin()->create(['name' => 'Other']);

        $post = BlogPost::factory()->create([
            'owned_by_user_id' => $owner->id,
            'status' => BlogPostStatus::Draft,
        ]);

        app(BlogPostEditLock::class)->acquire((int) $post->id, $owner);

        $this->expectException(ValidationException::class);

        app(SaveBlogPost::class)->handle($other, $post, [
            'title' => 'تلاش غیرمجاز',
            'body' => '<p>x</p>',
            'publish' => false,
            'content_version' => $post->content_version,
        ], requireLock: true);
    }

    public function test_category_delete_nulls_posts(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateBlogCategory::class)
            ->fillForm([
                'name' => 'تحلیل تماس',
                'slug' => 'call-analysis',
                'description' => '<p>توضیح</p><script>bad()</script>',
                'icon' => 'headset',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = BlogCategory::query()->where('slug', 'call-analysis')->first();
        $this->assertNotNull($category);
        $this->assertStringNotContainsString('<script>', (string) $category->description);

        $post = BlogPost::factory()->published()->create([
            'category_id' => $category->id,
        ]);

        app(DeleteBlogCategory::class)->handle($user, $category);

        $this->assertDatabaseMissing('blog_categories', ['id' => $category->id]);
        $this->assertNull($post->fresh()->category_id);
    }

    public function test_persian_slug_collision_gets_suffix(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $category = BlogCategory::factory()->create();

        $first = BlogPost::factory()->create([
            'slug' => 'کیفیت-تماس',
            'category_id' => $category->id,
        ]);

        $draft = app(FindOrCreateRecoverableDraft::class)->handle($user);
        app(BlogPostEditLock::class)->acquire((int) $draft->id, $user);

        $second = app(SaveBlogPost::class)->handle($user, $draft, [
            'title' => 'کیفیت تماس',
            'slug' => 'کیفیت-تماس',
            'body' => '<p>متن</p>',
            'publish' => false,
            'category_id' => $category->id,
            'content_version' => $draft->content_version,
        ]);

        $this->assertNotSame($first->slug, $second->slug);
        $this->assertStringStartsWith('کیفیت-تماس', $second->slug);
    }

    public function test_html_sanitizer_strips_scripts_and_unsafe_urls(): void
    {
        $sanitizer = app(HtmlSanitizer::class);

        $clean = $sanitizer->sanitize('<p onclick="x">سلام</p><a href="javascript:alert(1)">x</a><script>bad()</script>');

        $this->assertStringNotContainsString('script', strtolower($clean));
        $this->assertStringNotContainsString('onclick', strtolower($clean));
        $this->assertStringNotContainsString('javascript:', strtolower($clean));
        $this->assertFalse($sanitizer->isSafeUrl('javascript:alert(1)'));
        $this->assertFalse($sanitizer->isSafeUrl('//evil.test/x'));
        $this->assertTrue($sanitizer->isSafeUrl('https://avayar.org/blog'));
    }

    public function test_edit_page_autosave_and_lock_for_owner(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $post = app(FindOrCreateRecoverableDraft::class)->handle($user);

        Livewire::test(EditBlogPost::class, ['record' => $post->id])
            ->assertSet('hasWriteLock', true)
            ->fillForm([
                'title' => 'ویرایش زنده',
                'body' => '<p>بدنه</p>',
                'publish' => false,
            ])
            ->call('autosaveDraft')
            ->assertSet('autosaveStatus', 'saved');

        $this->assertSame('ویرایش زنده', $post->fresh()->title);
        $this->assertSame(BlogPostStatus::Draft, $post->fresh()->status);
    }
}
