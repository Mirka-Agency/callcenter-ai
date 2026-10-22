<?php

namespace Database\Factories;

use App\Domain\Blog\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'body' => '<p>'.fake()->paragraph().'</p><p>'.fake()->paragraph().'</p>',
            'excerpt' => fake()->sentence(12),
            'status' => BlogPostStatus::Draft,
            'category_id' => BlogCategory::factory(),
            'published_at' => null,
            'is_recoverable_draft' => false,
            'faq_json' => [],
            'content_version' => 1,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => BlogPostStatus::Published,
            'published_at' => now('UTC')->subDay(),
            'is_recoverable_draft' => false,
        ]);
    }

    public function recoverableDraft(?User $owner = null): static
    {
        return $this->state(fn () => [
            'title' => BlogPost::PLACEHOLDER_TITLE,
            'status' => BlogPostStatus::Draft,
            'is_recoverable_draft' => true,
            'owned_by_user_id' => $owner?->id ?? User::factory()->superAdmin(),
            'published_at' => null,
        ]);
    }
}
