<?php

namespace App\Models\Concerns;

use App\Services\Admin\AdminAuditLogger;

trait AuditedByAdmins
{
    public static function bootAuditedByAdmins(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(fn ($model) => app(AdminAuditLogger::class)->logModelEvent($event, $model));
        }
    }
}
