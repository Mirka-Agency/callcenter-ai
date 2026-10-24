<?php

namespace App\Models;

use App\Domain\Platform\Enums\AnnouncementAudience;
use App\Domain\Platform\Enums\AnnouncementLevel;
use App\Enums\UserRole;
use App\Models\Concerns\AuditedByAdmins;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'body', 'level', 'audience', 'starts_at', 'ends_at', 'is_active', 'created_by_user_id'])]
class Announcement extends Model
{
    use AuditedByAdmins;

    protected function casts(): array
    {
        return [
            'level' => AnnouncementLevel::class,
            'audience' => AnnouncementAudience::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $audiences = [AnnouncementAudience::All->value];

        if ($user->role === UserRole::Employer) {
            $audiences[] = AnnouncementAudience::Employers->value;
        } elseif ($user->role === UserRole::Employee) {
            $audiences[] = AnnouncementAudience::Employees->value;
        }

        return $query
            ->where('is_active', true)
            ->whereIn('audience', $audiences)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
