<?php

namespace App\Application\Blog\Actions;

use App\Application\Blog\BlogAuthorization;
use App\Application\Blog\Support\BlogPostEditLock;
use App\Models\BlogPost;
use App\Models\User;

class DeleteBlogPost
{
    public function __construct(
        private readonly BlogAuthorization $auth,
        private readonly BlogPostEditLock $lock,
    ) {}

    public function handle(User $actor, BlogPost $post): void
    {
        $this->auth->assertCanManage($actor);
        $this->lock->forceRelease((int) $post->id);
        $post->delete();
    }
}
