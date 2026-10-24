<?php

namespace App\Models;

use App\Models\Concerns\AuditedByAdmins;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['model_name', 'input_cost_per_million_rials', 'output_cost_per_million_rials', 'effective_from'])]
class AiCostRate extends Model
{
    use AuditedByAdmins;

    protected function casts(): array
    {
        return [
            'input_cost_per_million_rials' => 'integer',
            'output_cost_per_million_rials' => 'integer',
            'effective_from' => 'date',
        ];
    }
}
