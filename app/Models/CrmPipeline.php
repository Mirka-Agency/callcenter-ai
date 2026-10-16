<?php

namespace App\Models;

use App\Domain\Crm\Deals\PipelineStagePalette;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'organization_id',
    'name',
    'description',
    'is_default',
    'is_active',
    'show_win_probability',
])]
class CrmPipeline extends Model
{
    protected static function booted(): void
    {
        static::saving(function (CrmPipeline $pipeline): void {
            if (! $pipeline->organization_id) {
                throw new \RuntimeException('Pipelines must belong to an organization.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'show_win_probability' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<CrmPipelineStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(CrmPipelineStage::class)->orderBy('position');
    }

    /** @return HasMany<CrmDeal, $this> */
    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class);
    }

    public static function assertName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages([
                'name' => 'نام پایپ‌لاین الزامی است و حداکثر ۱۲۰ نویسه دارد.',
            ]);
        }

        return $name;
    }

    public static function assertDescription(?string $description): ?string
    {
        $description = trim((string) $description);

        if ($description === '') {
            return null;
        }

        if (mb_strlen($description) > 2000) {
            throw ValidationException::withMessages([
                'description' => 'توضیح پایپ‌لاین حداکثر ۲۰۰۰ نویسه دارد.',
            ]);
        }

        return $description;
    }

    public function seedDefaultStages(?string $kind = null): void
    {
        foreach (PipelineStagePalette::stagesForKind($kind) as $index => $stage) {
            $this->stages()->create([
                'organization_id' => $this->organization_id,
                'name' => $stage['name'],
                'color' => $stage['color'],
                'position' => $index + 1,
                'is_active' => true,
            ]);
        }
    }
}
