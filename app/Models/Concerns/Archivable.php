<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Archivable
{
    public function archive(): void
    {
        $this->forceFill(['archived_at' => now()])->save();
    }

    public function restoreFromArchive(): void
    {
        $this->forceFill(['archived_at' => null])->save();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('archived_at'));
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull($query->qualifyColumn('archived_at'));
    }
}
