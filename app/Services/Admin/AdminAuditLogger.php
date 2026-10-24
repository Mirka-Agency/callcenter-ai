<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Records changes made by admin-panel users. Changes made by customers,
 * jobs or the console are not audited here.
 */
class AdminAuditLogger
{
    /** Attributes that must never be written to the audit trail. */
    private const REDACTED = ['password', 'remember_token', 'credentials', 'webhook_token'];

    /** Bookkeeping attributes whose changes are not worth an audit entry. */
    private const IGNORED = ['updated_at', 'last_login_at', 'remember_token', 'last_polled_at'];

    public function log(string $action, ?Model $subject = null, array $changes = []): ?AdminAuditLog
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->role->canAccessAdminPanel()) {
            return null;
        }

        return AdminAuditLog::query()->create([
            'user_id' => $user->id,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'auditable_label' => $subject ? $this->label($subject) : null,
            'changes' => $changes === [] ? null : $this->redact($changes),
            'ip_address' => request()?->ip(),
        ]);
    }

    public function logModelEvent(string $event, Model $model): void
    {
        $changes = match ($event) {
            'created' => ['after' => $model->getAttributes()],
            'updated' => [
                'before' => array_intersect_key($model->getOriginal(), $model->getChanges()),
                'after' => $model->getChanges(),
            ],
            default => ['before' => $model->getOriginal()],
        };

        foreach (self::IGNORED as $attribute) {
            unset($changes['after'][$attribute], $changes['before'][$attribute]);
        }

        if ($event === 'updated' && $changes['after'] === []) {
            return;
        }

        $this->log($event, $model, $changes);
    }

    private function label(Model $model): string
    {
        foreach (['title', 'name', 'number', 'code', 'full_name', 'email', 'key'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_scalar($value) && $value !== '') {
                return class_basename($model).': '.$value;
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    private function redact(array $changes): array
    {
        array_walk_recursive($changes, function (mixed &$value, string|int $key): void {
            if (in_array($key, self::REDACTED, true)) {
                $value = '[redacted]';
            } elseif ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format(DATE_ATOM);
            }
        });

        return $changes;
    }
}
