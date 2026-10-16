<?php

namespace App\Application\Crm\Products\Actions;

use App\Application\Crm\Products\ProductAuthorization;
use App\Models\CrmProductCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateProductCategory
{
    public function __construct(private ProductAuthorization $auth) {}

    public function handle(int $organizationId, User $actor, string $name, string $icon = 'layers', string $tone = 'violet'): CrmProductCategory
    {
        $this->auth->assertInternalCrm($organizationId);
        $this->auth->authorize($this->auth->canManage($actor, $organizationId));

        $label = trim($name);
        if ($label === '' || mb_strlen($label) > 80) {
            throw ValidationException::withMessages([
                'category_name' => 'نام دسته‌بندی الزامی است و حداکثر ۸۰ نویسه دارد.',
            ]);
        }

        $slugBase = Str::slug($label);
        if ($slugBase === '') {
            $slugBase = 'category-'.Str::lower(Str::random(6));
        }

        $slug = $slugBase;
        $suffix = 1;
        while (
            CrmProductCategory::query()
                ->where('organization_id', $organizationId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $slugBase.'-'.$suffix;
            $suffix++;
        }

        $allowedIcons = ['monitor', 'chip', 'gear', 'layers', 'graduation', 'users', 'grid', 'box'];
        $allowedTones = ['sky', 'emerald', 'violet', 'amber', 'rose', 'indigo'];
        $icon = in_array($icon, $allowedIcons, true) ? $icon : 'layers';
        $tone = in_array($tone, $allowedTones, true) ? $tone : 'violet';

        return DB::transaction(function () use ($organizationId, $label, $slug, $icon, $tone): CrmProductCategory {
            $maxPosition = (int) CrmProductCategory::query()
                ->where('organization_id', $organizationId)
                ->max('position');

            return CrmProductCategory::query()->create([
                'organization_id' => $organizationId,
                'name' => $label,
                'slug' => $slug,
                'icon' => $icon,
                'tone' => $tone,
                'position' => $maxPosition + 10,
                'is_system' => false,
            ]);
        });
    }
}
