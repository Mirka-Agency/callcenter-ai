<?php

namespace App\Application\Blog\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class BlogPostEditLock
{
    public const TTL_SECONDS = 120;

    public function key(int $postId): string
    {
        return 'blog-post-edit-lock:'.$postId;
    }

    /**
     * @return array{user_id: int, user_name: string, acquired_at: string}|null
     */
    public function holder(int $postId): ?array
    {
        $payload = Cache::get($this->key($postId));

        return is_array($payload) ? $payload : null;
    }

    public function isHeldBy(int $postId, int $userId): bool
    {
        $holder = $this->holder($postId);

        return $holder !== null && (int) $holder['user_id'] === $userId;
    }

    /**
     * @return array{acquired: bool, holder: array{user_id: int, user_name: string, acquired_at: string}|null}
     */
    public function acquire(int $postId, User $user): array
    {
        $key = $this->key($postId);
        $holder = $this->holder($postId);

        if ($holder !== null && (int) $holder['user_id'] !== $user->id) {
            return ['acquired' => false, 'holder' => $holder];
        }

        $payload = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'acquired_at' => now('UTC')->toIso8601String(),
        ];

        Cache::put($key, $payload, self::TTL_SECONDS);

        return ['acquired' => true, 'holder' => $payload];
    }

    public function heartbeat(int $postId, User $user): bool
    {
        if (! $this->isHeldBy($postId, $user->id)) {
            return false;
        }

        $holder = $this->holder($postId);
        if ($holder === null) {
            return false;
        }

        Cache::put($this->key($postId), $holder, self::TTL_SECONDS);

        return true;
    }

    public function release(int $postId, User $user): void
    {
        if ($this->isHeldBy($postId, $user->id)) {
            Cache::forget($this->key($postId));
        }
    }

    public function forceRelease(int $postId): void
    {
        Cache::forget($this->key($postId));
    }
}
