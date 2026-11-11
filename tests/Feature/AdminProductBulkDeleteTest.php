<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'catalog_manager')->firstOrFail());
        $this->actingAs($user);
        $this->category = Category::create(['name_vi' => 'Danh mục', 'name_en' => 'Category', 'slug' => 'category']);
    }

    public function test_bulk_delete_removes_products_and_managed_images(): void
    {
        Storage::fake('product_images');
        $first = $this->product(1);
        $second = $this->product(2);
        Storage::disk('product_images')->put($first->id.'/photo.jpg', 'image');
        ProductImage::create(['product_id' => $first->id, 'image_url' => $first->id.'/photo.jpg', 'is_primary' => true]);

        $response = $this->postJson(route('admin.products.bulk-delete'), ['ids' => [$first->id, $second->id], 'page' => 1]);

        $response->assertOk()->assertJson(['deleted_ids' => [$first->id, $second->id], 'blocked' => [], 'missing_ids' => [], 'total' => 0, 'page' => 1]);
        $this->assertDatabaseMissing('products', ['id' => $first->id]);
        $this->assertDatabaseMissing('products', ['id' => $second->id]);
        Storage::disk('product_images')->assertMissing($first->id.'/photo.jpg');
    }

    public function test_bulk_delete_reports_partial_success_and_missing_ids(): void
    {
        $deletable = $this->product(1);
        $blocked = $this->product(2);
        $cart = Cart::create(['session_token' => 'bulk-cart']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $blocked->id, 'quantity' => 1]);

        $this->postJson(route('admin.products.bulk-delete'), ['ids' => [$deletable->id, $blocked->id, 999999]])
            ->assertOk()
            ->assertJsonPath('deleted_ids.0', $deletable->id)
            ->assertJsonPath('blocked.0.id', $blocked->id)
            ->assertJsonPath('missing_ids.0', 999999);

        $this->assertDatabaseMissing('products', ['id' => $deletable->id]);
        $this->assertDatabaseHas('products', ['id' => $blocked->id]);
    }

    public function test_bulk_delete_validates_selection_and_permission(): void
    {
        $this->postJson(route('admin.products.bulk-delete'), ['ids' => []])->assertUnprocessable()->assertJsonValidationErrors('ids');
        $this->postJson(route('admin.products.bulk-delete'), ['ids' => array_fill(0, 16, 1)])->assertUnprocessable()->assertJsonValidationErrors(['ids', 'ids.1']);

        $role = Role::where('name', 'catalog_manager')->firstOrFail();
        $role->permissions()->sync($role->permissions()->where('name', 'products.view')->pluck('permissions.id'));
        $this->postJson(route('admin.products.bulk-delete'), ['ids' => [$this->product(1)->id]])->assertForbidden();
    }

    public function test_bulk_delete_moves_back_when_last_page_becomes_empty(): void
    {
        for ($i = 1; $i <= 16; $i++) { $products[] = $this->product($i); }
        $lastPageProduct = Product::orderBy('id')->firstOrFail();

        $this->postJson(route('admin.products.bulk-delete'), ['ids' => [$lastPageProduct->id], 'page' => 2])
            ->assertOk()->assertJson(['page' => 1, 'total' => 15]);
    }

    private function product(int $number): Product
    {
        return Product::create([
            'category_id' => $this->category->id, 'sku' => 'BULK-'.$number, 'name_vi' => 'Sản phẩm '.$number,
            'name_en' => 'Product '.$number, 'slug' => 'bulk-'.$number, 'price' => 100, 'quantity' => 1,
            'status' => true, 'is_featured' => false,
        ]);
    }
}
