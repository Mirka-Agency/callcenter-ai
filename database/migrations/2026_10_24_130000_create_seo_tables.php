<?php

use Database\Support\IdempotentSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        IdempotentSchema::create('seo_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source_path', 1000);
            $table->string('match_type', 10)->default('exact');
            $table->string('target_url', 1000)->nullable();
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_path', 'match_type']);
        });

        IdempotentSchema::tableIfMissingColumns('blog_categories', ['meta_title'], function (Blueprint $table) {
            $table->string('meta_title', 300)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url', 1000)->nullable();
            $table->string('og_title', 300)->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image_url', 1000)->nullable();
            $table->json('faq_json')->nullable();
            $table->boolean('noindex')->default(false);
        });

        IdempotentSchema::tableIfMissingColumns('blog_posts', ['noindex'], function (Blueprint $table) {
            $table->boolean('noindex')->default(false);
        });
    }

    public function down(): void
    {
        IdempotentSchema::dropColumnsIfExist('blog_posts', 'noindex');
        IdempotentSchema::dropColumnsIfExist('blog_categories', 'meta_title', 'meta_description', 'canonical_url', 'og_title', 'og_description', 'og_image_url', 'faq_json', 'noindex');
        Schema::dropIfExists('seo_redirects');
    }
};
