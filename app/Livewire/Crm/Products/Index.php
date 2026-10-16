<?php

namespace App\Livewire\Crm\Products;

use App\Application\Crm\Products\Actions\CreateProduct;
use App\Application\Crm\Products\Actions\CreateProductCategory;
use App\Application\Crm\Products\Actions\DeleteProduct;
use App\Application\Crm\Products\Actions\EnsureProductCategories;
use App\Application\Crm\Products\Actions\ImportProducts;
use App\Application\Crm\Products\Actions\UpdateProduct;
use App\Application\Crm\Products\ProductAuthorization;
use App\Application\Crm\Products\ProductQuery;
use App\Domain\Crm\Products\ProductType;
use App\Livewire\Crm\Concerns\ResolvesCrmContext;
use App\Support\PersianNumber;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('محصولات و خدمات')]
class Index extends Component
{
    use ResolvesCrmContext;
    use WithFileUploads;
    use WithPagination;

    #[Url(except: 'all')]
    public string $type = 'all';

    #[Url(except: 'all')]
    public string $category = 'all';

    #[Url(except: '')]
    public string $search = '';

    public string $searchInput = '';

    public bool $showForm = false;

    public bool $showImport = false;

    public bool $showCategoryForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $typeInput = 'service';

    public string $categoryInput = '';

    public string $code = '';

    public string $priceTomans = '';

    public string $unitQuantity = '';

    public string $unitLabel = '';

    public bool $isActive = true;

    public string $categoryName = '';

    public $importFile = null;

    public function mount(EnsureProductCategories $ensureCategories): void
    {
        $this->searchInput = $this->search;
        $ensureCategories->handle($this->crmOrganizationId());
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function setType(string $type): void
    {
        $this->type = in_array($type, ['all', 'product', 'service'], true) ? $type : 'all';
        $this->resetPage();
    }

    public function setCategory(string $category): void
    {
        $this->category = $category === '' ? 'all' : $category;
        $this->resetPage();
    }

    public function applySearch(): void
    {
        $this->search = trim($this->searchInput);
        $this->resetPage();
    }

    public function updatedSearchInput(): void
    {
        $this->search = trim($this->searchInput);
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $productId, ProductQuery $query): void
    {
        $product = $query->findVisible($this->crmOrganizationId(), $this->crmUser(), $productId);

        $this->editingId = (int) $product->id;
        $this->name = (string) $product->name;
        $this->description = (string) ($product->description ?? '');
        $this->typeInput = $product->type->value;
        $this->categoryInput = $product->category_id ? (string) $product->category_id : '';
        $this->code = (string) ($product->code ?? '');
        $this->priceTomans = $product->price_tomans !== null ? (string) $product->price_tomans : '';
        $this->unitQuantity = $product->unit_quantity !== null
            ? (fmod((float) $product->unit_quantity, 1.0) === 0.0
                ? (string) (int) $product->unit_quantity
                : rtrim(rtrim(number_format((float) $product->unit_quantity, 2, '.', ''), '0'), '.'))
            : '';
        $this->unitLabel = (string) ($product->unit_label ?? '');
        $this->isActive = (bool) $product->is_active;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function openImport(): void
    {
        $this->reset('importFile');
        $this->showImport = true;
    }

    public function closeImport(): void
    {
        $this->showImport = false;
        $this->reset('importFile');
    }

    public function openCategoryForm(): void
    {
        $this->categoryName = '';
        $this->showCategoryForm = true;
    }

    public function closeCategoryForm(): void
    {
        $this->showCategoryForm = false;
        $this->categoryName = '';
    }

    public function saveCategory(CreateProductCategory $action): void
    {
        $category = $action->handle(
            $this->crmOrganizationId(),
            $this->crmUser(),
            $this->categoryName,
        );

        $this->closeCategoryForm();
        $this->category = (string) $category->id;
        $this->toast('دسته‌بندی جدید اضافه شد.');
    }

    public function save(CreateProduct $create, UpdateProduct $update, ProductQuery $query): void
    {
        $payload = [
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->typeInput,
            'category_id' => $this->categoryInput !== '' ? $this->categoryInput : null,
            'code' => $this->code,
            'price_tomans' => $this->priceTomans,
            'unit_quantity' => $this->unitQuantity,
            'unit_label' => $this->unitLabel,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId !== null) {
            $product = $query->findVisible($this->crmOrganizationId(), $this->crmUser(), $this->editingId);
            $update->handle($product, $this->crmUser(), $payload);
            $this->toast('مورد به‌روزرسانی شد.');
        } else {
            $create->handle($this->crmOrganizationId(), $this->crmUser(), $payload);
            $this->toast('مورد جدید ثبت شد.');
        }

        $this->closeForm();
    }

    public function remove(int $productId, DeleteProduct $action, ProductQuery $query): void
    {
        $product = $query->findVisible($this->crmOrganizationId(), $this->crmUser(), $productId);
        $action->handle($product, $this->crmUser());
        $this->toast('مورد حذف شد.');
    }

    public function import(ImportProducts $action): void
    {
        $this->validate([
            'importFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [], [
            'importFile' => 'فایل ایمپورت',
        ]);

        $result = $action->handle($this->crmOrganizationId(), $this->crmUser(), $this->importFile);
        $this->closeImport();
        $this->toast(sprintf(
            'ایمپورت انجام شد: %s جدید، %s به‌روز، %s رد شده.',
            PersianNumber::format($result['created']) ?? (string) $result['created'],
            PersianNumber::format($result['updated']) ?? (string) $result['updated'],
            PersianNumber::format($result['skipped']) ?? (string) $result['skipped'],
        ));
    }

    public function downloadTemplate(): StreamedResponse
    {
        $filename = 'avayar-products-template.csv';

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['name', 'description', 'type', 'code', 'price_tomans', 'unit_quantity', 'unit_label', 'is_active']);
            fputcsv($out, ['بسته‌ی افزایشی پیامک', 'توضیح نمونه', 'product', 'SMS-01', '1404000', '6000', 'عدد', '1']);
            fputcsv($out, ['پشتیبانی ماهانه', 'خدمت نمونه', 'service', 'SUP-01', '2500000', '1', 'ماه', '1']);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function render(ProductQuery $query, ProductAuthorization $auth, EnsureProductCategories $ensureCategories)
    {
        $organizationId = $this->crmOrganizationId();
        $actor = $this->crmUser();
        $categories = $ensureCategories->handle($organizationId);
        $filters = [
            'type' => $this->type,
            'search' => $this->search,
            'category_id' => $this->category,
        ];

        return view('livewire.crm.products.index', [
            'products' => $query->paginate($organizationId, $actor, $filters),
            'counts' => $query->counts($organizationId, $actor, $filters),
            'categories' => $categories,
            'types' => ProductType::cases(),
            'canManage' => $auth->canManage($actor, $organizationId),
            'portal' => $this->crmPortal(),
        ])->layout($this->crmLayout());
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'name',
            'description',
            'code',
            'priceTomans',
            'unitQuantity',
            'unitLabel',
            'categoryInput',
        ]);
        $this->typeInput = 'service';
        $this->isActive = true;
        if ($this->category !== 'all') {
            $this->categoryInput = $this->category;
        }
    }
}
