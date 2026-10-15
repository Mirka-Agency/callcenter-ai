<?php

namespace App\Application\Crm\Products\Actions;

use App\Application\Crm\Products\ProductAuthorization;
use App\Domain\Crm\Products\ProductType;
use App\Models\CrmProduct;
use App\Models\User;
use App\Support\PersianNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProduct
{
    public function __construct(private ProductAuthorization $auth) {}

    /** @param  array<string, mixed>  $input */
    public function handle(int $organizationId, User $actor, array $input): CrmProduct
    {
        $this->auth->assertInternalCrm($organizationId);
        $this->auth->authorize($this->auth->canManage($actor, $organizationId));

        $payload = $this->validated($organizationId, $input);

        return DB::transaction(function () use ($organizationId, $actor, $payload): CrmProduct {
            return CrmProduct::query()->create([
                ...$payload,
                'organization_id' => $organizationId,
                'created_by_user_id' => $actor->id,
            ]);
        });
    }

    /** @param  array<string, mixed>  $input
     *  @return array{name: string, description: ?string, type: ProductType, code: ?string, price_tomans: ?int, unit_quantity: ?float, unit_label: ?string, is_active: bool}
     */
    private function validated(int $organizationId, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 180) {
            throw ValidationException::withMessages([
                'name' => 'نام الزامی است و حداکثر ۱۸۰ نویسه دارد.',
            ]);
        }

        $type = ProductType::tryFrom((string) ($input['type'] ?? ''));
        if (! $type instanceof ProductType) {
            throw ValidationException::withMessages([
                'type' => 'نوع باید محصول یا خدمت باشد.',
            ]);
        }

        $description = trim((string) ($input['description'] ?? ''));
        if (mb_strlen($description) > 5000) {
            throw ValidationException::withMessages([
                'description' => 'توضیحات حداکثر ۵۰۰۰ نویسه دارد.',
            ]);
        }

        $code = trim((string) ($input['code'] ?? ''));
        $code = $code === '' ? null : mb_substr($code, 0, 64);
        if ($code !== null) {
            $exists = CrmProduct::query()
                ->where('organization_id', $organizationId)
                ->where('code', $code)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'code' => 'این کد قبلاً ثبت شده است.',
                ]);
            }
        }

        return [
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'type' => $type,
            'code' => $code,
            'price_tomans' => $this->price($input['price_tomans'] ?? null),
            'unit_quantity' => $this->quantity($input['unit_quantity'] ?? null),
            'unit_label' => $this->unitLabel($input['unit_label'] ?? null),
            'is_active' => filter_var($input['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function price(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = PersianNumber::parse(trim((string) $value));
        if (! is_int($parsed) && ! (is_float($parsed) && floor($parsed) === $parsed)) {
            throw ValidationException::withMessages([
                'price_tomans' => 'قیمت باید عدد صحیح تومان باشد.',
            ]);
        }

        $price = (int) $parsed;
        if ($price < 0 || $price > 9_000_000_000_000_000) {
            throw ValidationException::withMessages([
                'price_tomans' => 'قیمت خارج از محدوده مجاز است.',
            ]);
        }

        return $price;
    }

    private function quantity(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = PersianNumber::parse(trim((string) $value));
        if (! is_int($parsed) && ! is_float($parsed)) {
            throw ValidationException::withMessages([
                'unit_quantity' => 'مقدار واحد نامعتبر است.',
            ]);
        }

        $qty = (float) $parsed;
        if ($qty < 0) {
            throw ValidationException::withMessages([
                'unit_quantity' => 'مقدار واحد نمی‌تواند منفی باشد.',
            ]);
        }

        return $qty;
    }

    private function unitLabel(mixed $value): ?string
    {
        $label = trim((string) ($value ?? ''));
        if ($label === '') {
            return null;
        }

        if (mb_strlen($label) > 64) {
            throw ValidationException::withMessages([
                'unit_label' => 'برچسب واحد حداکثر ۶۴ نویسه دارد.',
            ]);
        }

        return $label;
    }
}
