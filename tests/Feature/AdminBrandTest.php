<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBrandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'catalog_manager')->firstOrFail());
        $this->actingAs($user);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['name_vi' => 'Thương hiệu mới', 'name_en' => 'New brand', 'slug' => 'new-brand', 'image_url' => null, 'status' => '1'], $overrides);
    }

    public function test_create_edit_and_delete_preserves_products(): void
    {
        $this->get(route('admin.brands.create'))->assertOk();
        $this->post(route('admin.brands.store'), $this->payload(['slug' => '']))->assertRedirect(route('admin.brands.index'));
        $brand = Brand::firstOrFail();
        $this->assertSame('thuong-hieu-moi', $brand->slug);
        $this->get(route('admin.brands.edit', $brand))->assertOk();
        $this->put(route('admin.brands.update', $brand), $this->payload(['name_vi' => 'Tên mới']))->assertRedirect(route('admin.brands.index'));
        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'name_vi' => 'Tên mới']);
        $category = Category::create(['name_vi' => 'Test', 'name_en' => 'Test', 'slug' => 'test']);
        $product = Product::create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name_vi' => 'Test', 'name_en' => 'Test', 'sku' => 'TEST', 'slug' => 'test', 'price' => '10.00']);
        $this->delete(route('admin.brands.destroy', $brand))->assertRedirect(route('admin.brands.index'));
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
        $this->assertNull($product->fresh()->brand_id);
    }

    public function test_invalid_data_and_duplicate_slug_are_rejected(): void
    {
        Brand::create($this->payload());
        $this->post(route('admin.brands.store'), $this->payload(['name_vi' => '', 'image_url' => 'javascript:alert(1)']))->assertSessionHasErrors(['name_vi', 'slug', 'image_url']);
        $this->assertDatabaseCount('brands', 1);
    }

    public function test_pagination_search_and_write_permissions(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            Brand::create($this->payload(['slug' => 'brand-'.$i]));
        }
        $this->get(route('admin.brands.index'))->assertOk()->assertViewHas('brands', fn ($items) => $items->total() === 16 && $items->count() === 15);
        $this->get(route('admin.brands.index', ['q' => 'brand-16']))->assertOk()->assertViewHas('brands', fn ($items) => $items->total() === 1);
        $role = Role::where('name', 'catalog_manager')->firstOrFail();
        $role->permissions()->sync($role->permissions()->where('name', 'brands.view')->pluck('permissions.id'));
        $brand = Brand::firstOrFail();
        $this->get(route('admin.brands.index'))->assertOk()->assertDontSee(route('admin.brands.create'), false);
        $this->get(route('admin.brands.create'))->assertForbidden();
        $this->post(route('admin.brands.store'), $this->payload())->assertForbidden();
        $this->get(route('admin.brands.edit', $brand))->assertForbidden();
        $this->put(route('admin.brands.update', $brand), $this->payload())->assertForbidden();
        $this->delete(route('admin.brands.destroy', $brand))->assertForbidden();
    }
}
