<?php

namespace Tests\Feature;

use App\Application\Blog\Actions\FindOrCreateRecoverableDraft;
use App\Application\Blog\Actions\SaveBlogCategory;
use App\Application\Blog\Actions\SaveBlogPost;
use App\Application\Blog\Support\BlogPostEditLock;
use App\Domain\Blog\BlogPostStatus;
use App\Domain\Platform\Enums\AdminArea;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WriterRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_can_open_every_blog_post_and_category(): void
    {
        $writer = User::factory()->writer()->create();
        $otherAuthor = User::factory()->superAdmin()->create();
        $category = BlogCategory::query()->create(['name' => 'آموزش', 'slug' => 'learn']);
        $post = app(FindOrCreateRecoverableDraft::class)->handle($otherAuthor);

        $this->actingAs($writer);

        foreach ([
            '/admin/blog-posts',
            '/admin/blog-posts/'.$post->id.'/edit',
            '/admin/blog-categories',
            '/admin/blog-categories/'.$category->id.'/edit',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_writer_can_edit_posts_and_categories(): void
    {
        $writer = User::factory()->writer()->create();
        $this->actingAs($writer);

        $post = app(FindOrCreateRecoverableDraft::class)->handle($writer);
        app(BlogPostEditLock::class)->acquire((int) $post->id, $writer);

        $saved = app(SaveBlogPost::class)->handle($writer, $post, [
            'title' => 'مطلب نویسنده',
            'slug' => 'writer-post',
            'body' => '<p>متن</p>',
            'publish' => true,
            'author_user_id' => $writer->id,
            'content_version' => $post->content_version,
        ]);

        $this->assertSame(BlogPostStatus::Published, $saved->status);
        $this->assertSame($writer->id, $saved->author_user_id);

        app(SaveBlogCategory::class)->handle($writer, null, ['name' => 'اخبار', 'slug' => 'news']);
        $this->assertTrue(BlogCategory::query()->where('slug', 'news')->exists());
        $this->assertSame(1, BlogPost::query()->where('status', BlogPostStatus::Published->value)->count());
    }

    public function test_writer_cannot_open_anything_outside_the_blog(): void
    {
        $writer = User::factory()->writer()->create();
        $organization = Organization::factory()->create();
        $this->actingAs($writer);

        foreach ([
            '/admin/organizations',
            '/admin/organizations/'.$organization->id.'/overview',
            '/admin/users',
            '/admin/voip-providers',
            '/admin/crm-providers',
            '/admin/organization-voip-connections',
            '/admin/organization-crm-connections',
            '/admin/problem-inbox',
            '/admin/demo-requests',
            '/admin/finance-overview',
            '/admin/business-analytics',
            '/admin/announcements',
        ] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->assertTrue($writer->hasAdminArea(AdminArea::Content));
        $this->assertFalse($writer->hasAdminArea(AdminArea::Support));
        $this->get('/admin')->assertOk()->assertDontSee(__('filament.navigation.users'));
    }
}
