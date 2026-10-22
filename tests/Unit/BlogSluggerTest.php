<?php

namespace Tests\Unit;

use App\Application\Blog\Support\BlogSlugger;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogSluggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_keeps_persian_letters_and_collapses_whitespace(): void
    {
        $slugger = app(BlogSlugger::class);

        $slug = $slugger->fromTitle('کیفیت  مکالمه ها با  AI');

        $this->assertSame('کیفیت-مکالمه-ها-با-ai', $slug);
    }

    public function test_unique_post_slug_appends_suffix(): void
    {
        BlogPost::factory()->create(['slug' => 'test-slug']);

        $slugger = app(BlogSlugger::class);
        $unique = $slugger->uniquePostSlug('test-slug');

        $this->assertSame('test-slug-2', $unique);
    }
}
