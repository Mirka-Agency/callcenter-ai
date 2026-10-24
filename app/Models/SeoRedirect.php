<?php

namespace App\Models;

use App\Domain\Seo\RedirectStatus;
use App\Models\Concerns\AuditedByAdmins;
use App\Support\Seo\RedirectResolver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['source_path', 'match_type', 'target_url', 'status_code', 'is_active', 'notes', 'created_by_user_id'])]
class SeoRedirect extends Model
{
    use AuditedByAdmins;

    public const EXACT = 'exact';

    public const PREFIX = 'prefix';

    protected function casts(): array
    {
        return [
            'status_code' => RedirectStatus::class,
            'is_active' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SeoRedirect $redirect): void {
            $redirect->source_path = RedirectResolver::normalizePath($redirect->source_path);

            if ($redirect->status_code === RedirectStatus::Gone) {
                $redirect->target_url = null;
            }
        });

        static::saved(fn () => RedirectResolver::flush());
        static::deleted(fn () => RedirectResolver::flush());
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
