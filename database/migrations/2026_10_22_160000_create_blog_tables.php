<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->string('icon', 40)->nullable();
            $table->timestamps();
        });

        IdempotentSchema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 300);
            $table->string('slug')->unique();
            $table->longText('body');
            $table->text('excerpt')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->string('cover_image_url', 1000)->nullable();
            $table->string('cover_video_url', 1000)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_display_name')->nullable();
            $table->foreignId('owned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_recoverable_draft')->default(false);
            $table->string('meta_title', 300)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('seo_keywords', 1000)->nullable();
            $table->string('canonical_url', 1000)->nullable();
            $table->string('og_title', 300)->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image_url', 1000)->nullable();
            $table->json('faq_json')->nullable();
            $table->unsignedInteger('content_version')->default(1);
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['owned_by_user_id', 'is_recoverable_draft']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};
