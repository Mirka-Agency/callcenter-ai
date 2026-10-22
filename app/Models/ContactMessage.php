<?php

namespace App\Models;

use App\Enums\ContactMessageSubject;
use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'full_name',
    'email',
    'subject',
    'message',
    'ip_address',
    'archived_at',
])]
class ContactMessage extends Model
{
    use Archivable;

    protected function casts(): array
    {
        return [
            'subject' => ContactMessageSubject::class,
            'archived_at' => 'datetime',
        ];
    }
}
