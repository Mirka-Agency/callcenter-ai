<?php

namespace App\Application\Blog\Actions;

use App\Application\Blog\BlogAuthorization;
use App\Models\BlogCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteBlogCategory
{
    public function __construct(
        private readonly BlogAuthorization $auth,
    ) {}

    public function handle(User $actor, BlogCategory $category): void
    {
        $this->auth->assertCanManage($actor);

        DB::transaction(function () use ($category): void {
            $category->posts()->update(['category_id' => null]);
            $category->delete();
        });
    }
}
