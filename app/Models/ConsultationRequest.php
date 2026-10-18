<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'full_name',
    'phone',
    'company_name',
    'department',
    'notes',
    'ip_address',
    'archived_at',
])]
class ConsultationRequest extends Model
{
    use Archivable;

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }
}
