<?php

namespace App\Application\Crm\Products\Actions;

use App\Application\Crm\Products\ProductAuthorization;
use App\Domain\Crm\Products\ProductType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ImportProducts
{
    public function __construct(
        private ProductAuthorization $auth,
        private CreateProduct $create,
        private UpdateProduct $update,
    ) {}

    /** @return array{created: int, updated: int, skipped: int} */
    public function handle(int $organizationId, User $actor, UploadedFile $file): array
    {
        $this->auth->assertInternalCrm($organizationId);
        $this->auth->authorize($this->auth->canManage($actor, $organizationId));

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'import_file' => 'خواندن فایل ممکن نبود.',
            ]);
        }

        try {
            $header = fgetcsv($handle);
            if (! is_array($header) || $header === []) {
                throw ValidationException::withMessages([
                    'import_file' => 'فایل CSV خالی است.',
                ]);
            }

            $map = $this->headerMap($header);
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($this->rowEmpty($row)) {
                    $skipped++;

                    continue;
                }

                $payload = [
                    'name' => $this->cell($row, $map, 'name'),
                    'description' => $this->cell($row, $map, 'description'),
                    'type' => $this->normalizeType($this->cell($row, $map, 'type')),
                    'code' => $this->cell($row, $map, 'code'),
                    'price_tomans' => $this->cell($row, $map, 'price_tomans'),
                    'unit_quantity' => $this->cell($row, $map, 'unit_quantity'),
                    'unit_label' => $this->cell($row, $map, 'unit_label'),
                    'is_active' => $this->normalizeActive($this->cell($row, $map, 'is_active')),
                ];

                if (trim((string) $payload['name']) === '') {
                    $skipped++;

                    continue;
                }

                $code = trim((string) ($payload['code'] ?? ''));
                $existing = null;
                if ($code !== '') {
                    $existing = \App\Models\CrmProduct::query()
                        ->where('organization_id', $organizationId)
                        ->where('code', $code)
                        ->first();
                }

                try {
                    if ($existing) {
                        $this->update->handle($existing, $actor, $payload);
                        $updated++;
                    } else {
                        $this->create->handle($organizationId, $actor, $payload);
                        $created++;
                    }
                } catch (ValidationException) {
                    $skipped++;
                }
            }

            return compact('created', 'updated', 'skipped');
        } finally {
            fclose($handle);
        }
    }

    /** @param  list<string|null>  $header
     *  @return array<string, int>
     */
    private function headerMap(array $header): array
    {
        $aliases = [
            'name' => ['name', 'نام'],
            'description' => ['description', 'توضیحات'],
            'type' => ['type', 'نوع'],
            'code' => ['code', 'کد'],
            'price_tomans' => ['price_tomans', 'price', 'قیمت', 'قیمت_تومان'],
            'unit_quantity' => ['unit_quantity', 'quantity', 'مقدار_واحد'],
            'unit_label' => ['unit_label', 'unit', 'واحد'],
            'is_active' => ['is_active', 'active', 'وضعیت'],
        ];

        $map = [];
        foreach ($header as $index => $raw) {
            $key = mb_strtolower(trim((string) $raw));
            foreach ($aliases as $field => $names) {
                if (in_array($key, array_map('mb_strtolower', $names), true)) {
                    $map[$field] = $index;
                }
            }
        }

        if (! isset($map['name']) || ! isset($map['type'])) {
            throw ValidationException::withMessages([
                'import_file' => 'ستون‌های نام و نوع در فایل الزامی هستند.',
            ]);
        }

        return $map;
    }

    /** @param  list<string|null>  $row
     *  @param  array<string, int>  $map
     */
    private function cell(array $row, array $map, string $field): ?string
    {
        if (! isset($map[$field])) {
            return null;
        }

        $value = $row[$map[$field]] ?? null;

        return $value === null ? null : (string) $value;
    }

    /** @param  list<string|null>  $row */
    private function rowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeType(?string $value): string
    {
        $raw = mb_strtolower(trim((string) $value));

        return match ($raw) {
            'product', 'محصول', 'کالا' => ProductType::Product->value,
            'service', 'خدمت', 'سرویس' => ProductType::Service->value,
            default => $raw,
        };
    }

    private function normalizeActive(?string $value): bool
    {
        $raw = mb_strtolower(trim((string) $value));
        if ($raw === '') {
            return true;
        }

        return ! in_array($raw, ['0', 'false', 'no', 'inactive', 'غیرفعال'], true);
    }
}
