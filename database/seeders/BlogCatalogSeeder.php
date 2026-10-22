<?php

namespace Database\Seeders;

use App\Application\Blog\Support\HtmlSanitizer;
use App\Domain\Blog\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Support\Blog\BlogCatalog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BlogCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $sanitizer = app(HtmlSanitizer::class);
        $payload = BlogCatalog::seedData();
        $categoryIds = [];

        foreach ($payload['categories'] as $category) {
            $record = BlogCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['label'],
                    'description' => '',
                    'icon' => $category['icon'],
                ],
            );
            $categoryIds[$category['slug']] = $record->id;
        }

        foreach ($payload['posts'] as $post) {
            $paragraphs = $post['body'] ?? [];
            $bodyHtml = collect($paragraphs)
                ->map(fn (string $paragraph): string => '<p>'.e($paragraph).'</p>')
                ->implode('');

            BlogPost::query()->updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'title' => $post['title'],
                    'body' => $sanitizer->sanitize($bodyHtml),
                    'excerpt' => $post['excerpt'],
                    'status' => BlogPostStatus::Published,
                    'category_id' => $categoryIds[$post['category']] ?? null,
                    'published_at' => Carbon::parse($post['published_on_sort'], 'UTC')->startOfDay(),
                    'is_recoverable_draft' => false,
                    'meta_title' => $post['title'],
                    'meta_description' => $post['excerpt'],
                    'faq_json' => [],
                    'content_version' => 1,
                ],
            );
        }
    }
}
