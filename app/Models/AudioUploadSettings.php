<?php

namespace App\Models;

use App\Models\Concerns\AuditedByAdmins;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'max_file_size_bytes',
    'max_duration_seconds',
    'allowed_extensions',
    'is_active',
])]
class AudioUploadSettings extends Model
{
    use AuditedByAdmins;

    protected function casts(): array
    {
        return [
            'allowed_extensions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'max_file_size_bytes' => 52_428_800,
            'max_duration_seconds' => 3600,
            'allowed_extensions' => ['mp3', 'wav', 'm4a', 'ogg', 'flac'],
            'is_active' => true,
        ]);
    }
}
