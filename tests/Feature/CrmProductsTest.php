<?php

namespace Tests\Feature;

use App\Application\Crm\Products\Actions\CreateProduct;
use App\Domain\Crm\OrganizationCrmMode;
use App\Domain\Crm\Products\ProductType;
use App\Enums\UserRole;
use App\Livewire\Crm\Products\Index as ProductsIndex;
use App\Models\CrmProduct;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class CrmProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_page_lists_catalog_items(): void
    {
        [$organization, $manager] = $this->organization();

        app(CreateProduct::class)->handle($organization->id, $manager, [
            'name' => 'بسته‌ی افزایشی پیامک',
            'description' => 'افزایش سقف پیامک سازمانی',
            'type' => ProductType::Product->value,
            'price_tomans' => 1404000,
            'unit_quantity' => 6000,
            'unit_label' => 'عدد',
        ]);

        $this->actingAs($manager)
            ->get(route('employer.crm.products.index'))
            ->assertOk()
            ->assertSee('محصولات و خدمات', false)
            ->assertSee('بسته‌ی افزایشی پیامک', false)
            ->assertSee('فعال', false);
    }

    public function test_can_create_edit_and_delete_product_from_livewire(): void
    {
        [$organization, $manager] = $this->organization();

        Livewire::actingAs($manager)
            ->test(ProductsIndex::class)
            ->call('openCreate')
            ->set('name', 'پشتیبانی ماهانه')
            ->set('description', 'خدمت پشتیبانی')
            ->set('typeInput', ProductType::Service->value)
            ->set('code', 'SUP-01')
            ->set('priceTomans', '2500000')
            ->set('unitQuantity', '1')
            ->set('unitLabel', 'ماه')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('پشتیبانی ماهانه', false);

        $product = CrmProduct::query()
            ->where('organization_id', $organization->id)
            ->where('code', 'SUP-01')
            ->first();

        $this->assertNotNull($product);
        $this->assertSame(ProductType::Service, $product->type);
        $this->assertSame(2500000, $product->price_tomans);

        Livewire::actingAs($manager)
            ->test(ProductsIndex::class)
            ->call('openEdit', $product->id)
            ->set('name', 'پشتیبانی ویژه')
            ->set('priceTomans', '3000000')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('پشتیبانی ویژه', false);

        $this->assertSame('پشتیبانی ویژه', $product->fresh()->name);
        $this->assertSame(3000000, $product->fresh()->price_tomans);

        Livewire::actingAs($manager)
            ->test(ProductsIndex::class)
            ->call('remove', $product->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted($product);
    }

    public function test_type_filter_and_search_work(): void
    {
        [$organization, $manager] = $this->organization();

        app(CreateProduct::class)->handle($organization->id, $manager, [
            'name' => 'محصول آلفا',
            'type' => ProductType::Product->value,
            'code' => 'P-A',
        ]);
        app(CreateProduct::class)->handle($organization->id, $manager, [
            'name' => 'خدمت بتا',
            'type' => ProductType::Service->value,
            'code' => 'S-B',
        ]);

        Livewire::actingAs($manager)
            ->test(ProductsIndex::class)
            ->call('setType', 'product')
            ->assertSee('محصول آلفا', false)
            ->assertDontSee('خدمت بتا', false)
            ->call('setType', 'all')
            ->set('searchInput', 'S-B')
            ->call('applySearch')
            ->assertSee('خدمت بتا', false)
            ->assertDontSee('محصول آلفا', false);
    }

    public function test_starts_without_categories_and_can_add_and_filter(): void
    {
        [$organization, $manager] = $this->organization();

        Livewire::actingAs($manager)
            ->test(ProductsIndex::class)
            ->assertSee('دسته‌بندی محصولات', false)
            ->assertSee('افزودن دسته‌بندی', false)
            ->assertDontSee('نرم‌افزار', false)
            ->assertDontSee('سخت‌افزار', false)
            ->call('openCategoryForm')
            ->set('categoryName', 'نرم‌افزار')
            ->call('saveCategory')
            ->assertHasNoErrors()
            ->assertSee('نرم‌افزار', false);

        $software = \App\Models\CrmProductCategory::query()
            ->where('organization_id', $organization->id)
            ->where('name', 'نرم‌افزار')
            ->firstOrFail();

        app(CreateProduct::class)->handle($organization->id, $manager, [
            'name' => 'لایسنس CRM',
            'type' => ProductType::Product->value,
            'code' => 'SW-1',
            'category_id' => $software->id,
        ]);
        app(CreateProduct::class)->handle($organization->id, $manager, [
            'name' => 'مشاوره فروش',
            'type' => ProductType::Service->value,
            'code' => 'CN-1',
        ]);

        Livewire::actingAs($manager)
            ->test(ProductsIndex::class)
            ->call('setCategory', (string) $software->id)
            ->assertSee('لایسنس CRM', false)
            ->assertDontSee('مشاوره فروش', false);

        $this->assertDatabaseHas('crm_product_categories', [
            'organization_id' => $organization->id,
            'name' => 'نرم‌افزار',
            'is_system' => false,
        ]);
    }

    public function test_import_csv_creates_products(): void
    {
        [$organization, $manager] = $this->organization();

        $csv = UploadedFile::fake()->createWithContent(
            'products.csv',
            "name,type,code,price_tomans,unit_quantity,unit_label,is_active\n".
            "خدمت گاما,service,S-G,1000000,10,دقیقه,1\n"
        );

        Livewire::actingAs($manager)
            ->test(ProductsIndex::class)
            ->call('openImport')
            ->set('importFile', $csv)
            ->call('import')
            ->assertHasNoErrors()
            ->assertSee('خدمت گاما', false);

        $this->assertDatabaseHas('crm_products', [
            'organization_id' => $organization->id,
            'code' => 'S-G',
            'type' => ProductType::Service->value,
            'price_tomans' => 1000000,
        ]);
    }

    public function test_cross_tenant_product_is_not_visible(): void
    {
        [$organization, $manager] = $this->organization();
        [$otherOrganization, $otherManager] = $this->organization();

        app(CreateProduct::class)->handle($otherOrganization->id, $otherManager, [
            'name' => 'محصول سازمان دیگر',
            'type' => ProductType::Product->value,
            'code' => 'OTHER-1',
        ]);

        Livewire::actingAs($manager)
            ->test(ProductsIndex::class)
            ->assertDontSee('محصول سازمان دیگر', false);

        $this->assertSame(0, CrmProduct::query()->where('organization_id', $organization->id)->count());
    }

    public function test_external_crm_blocks_products_route(): void
    {
        [$organization, $manager] = $this->organization();
        $organization->update(['crm_mode' => OrganizationCrmMode::External]);

        $this->actingAs($manager)
            ->get(route('employer.crm.products.index'))
            ->assertNotFound();
    }

    /** @return array{0: Organization, 1: User, 2: OrganizationUser} */
    private function organization(): array
    {
        $manager = User::factory()->create(['role' => UserRole::Employer]);
        $organization = Organization::factory()->create([
            'user_id' => $manager->id,
            'crm_mode' => OrganizationCrmMode::Internal,
        ]);
        $agent = OrganizationUser::query()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['role' => UserRole::Employee])->id,
            'first_name' => 'سارا',
            'last_name' => 'نوری',
            'is_active' => true,
        ]);

        return [$organization, $manager, $agent];
    }
}
