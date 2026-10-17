<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'full_name',
    'phone',
    'business_domain',
    'company_name',
    'website_url',
    'ip_address',
])]
class DemoRequest extends Model {}
