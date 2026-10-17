<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'full_name',
    'phone',
    'business_domain',
    'company_name',
    'website_url',
    'ip_address',
    'archived_at',
])]
class DemoRequest extends Model
{
    use Archivable;

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }
}
