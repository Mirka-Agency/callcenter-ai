<?php

namespace App\Application\Blog\Actions;

use App\Application\Blog\BlogAuthorization;
use App\Application\Blog\Support\BlogPostEditLock;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DiscardRecoverableDraft
{
    public function __construct(
        private readonly BlogAuthorization $auth,
        private readonly BlogPostEditLock $lock,
    ) {}

    public function handle(User $actor, BlogPost $post): void
    {
        $this->auth->assertCanManage($actor);

        if (! $post->is_recoverable_draft || (int) $post->owned_by_user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'draft' => __('filament.blog.errors.draft_not_recoverable'),
            ]);
        }

        $this->lock->forceRelease((int) $post->id);
        $post->delete();
    }
}
