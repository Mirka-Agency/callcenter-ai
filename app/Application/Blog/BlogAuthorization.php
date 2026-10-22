<?php

namespace App\Application\Blog;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class BlogAuthorization
{
    public function canManage(User $actor): bool
    {
        return $actor->role === UserRole::SuperAdmin;
    }

    public function canSelectAsAuthor(User $candidate): bool
    {
        return in_array($candidate->role, [UserRole::SuperAdmin, UserRole::Admin], true);
    }

    public function authorize(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException(__('filament.blog.errors.unauthorized'));
        }
    }

    public function assertCanManage(User $actor): void
    {
        $this->authorize($this->canManage($actor));
    }
}
