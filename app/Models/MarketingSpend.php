<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['spent_on', 'channel', 'amount_rials', 'notes'])]
class MarketingSpend extends Model
{
    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'amount_rials' => 'integer',
        ];
    }
}
