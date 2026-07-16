<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Auth\Events\Login;

class RecordUserLastLogin
{
    public function __construct(private ImpersonationService $impersonation) {}

    public function handle(Login $event): void
    {
        if ($this->impersonation->isImpersonating()) {
            return;
        }

        if (! $event->user instanceof User) {
            return;
        }

        $event->user->newQuery()
            ->whereKey($event->user->getKey())
            ->update(['last_login_at' => now()]);
    }
}
