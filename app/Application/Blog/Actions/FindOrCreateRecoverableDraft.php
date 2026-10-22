<?php

namespace App\Application\Blog\Actions;

use App\Application\Blog\BlogAuthorization;
use App\Application\Blog\Support\BlogSlugger;
use App\Domain\Blog\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FindOrCreateRecoverableDraft
{
    public function __construct(
        private readonly BlogAuthorization $auth,
        private readonly BlogSlugger $slugger,
    ) {}

    public function handle(User $actor): BlogPost
    {
        $this->auth->assertCanManage($actor);

        $lock = Cache::lock('blog-recoverable-draft:'.$actor->id, 10);

        return $lock->block(5, function () use ($actor): BlogPost {
            return DB::transaction(function () use ($actor): BlogPost {
                $existing = BlogPost::query()
                    ->where('owned_by_user_id', $actor->id)
                    ->where('status', BlogPostStatus::Draft)
                    ->where('is_recoverable_draft', true)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof BlogPost) {
                    return $existing;
                }

                $baseSlug = $this->slugger->fromTitle('draft-'.$actor->id.'-'.Str::lower(Str::random(6)));

                return BlogPost::query()->create([
                    'title' => BlogPost::PLACEHOLDER_TITLE,
                    'slug' => $this->slugger->uniquePostSlug($baseSlug),
                    'body' => '',
                    'excerpt' => null,
                    'status' => BlogPostStatus::Draft,
                    'owned_by_user_id' => $actor->id,
                    'is_recoverable_draft' => true,
                    'author_user_id' => $actor->id,
                    'author_display_name' => $actor->name,
                    'faq_json' => [],
                    'content_version' => 1,
                ]);
            });
        });
    }
}
