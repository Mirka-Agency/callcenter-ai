<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'full_name',
    'phone',
    'company_name',
    'department',
    'notes',
    'ip_address',
])]
class ConsultationRequest extends Model
{
}
