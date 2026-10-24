<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserActivityDay;
use App\Services\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks customer users (employers and employees) as active for today, at most one write per user per day.
 * Admin users and impersonated sessions are not counted.
 */
class RecordUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if (! $user instanceof User || $user->role->canAccessAdminPanel()) {
            return $response;
        }

        if (app(ImpersonationService::class)->isImpersonating()) {
            return $response;
        }

        $today = now()->toDateString();

        Cache::remember("user-activity:{$user->id}:{$today}", now()->endOfDay(), function () use ($user, $today): bool {
            UserActivityDay::query()->insertOrIgnore([
                'user_id' => $user->id,
                'role' => $user->role->value,
                'activity_date' => $today,
                'created_at' => now(),
            ]);

            return true;
        });

        return $response;
    }
}
