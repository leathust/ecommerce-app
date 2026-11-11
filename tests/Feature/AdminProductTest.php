<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'catalog_manager')->firstOrFail());
        $this->actingAs($user);
        $category = Category::create(['name_vi' => 'Điện thoại', 'name_en' => 'Phones', 'slug' => 'phones']);
        $this->data = ['category_id' => $category->id, 'brand_id' => null, 'sku' => 'PHONE-1', 'name_vi' => 'Điện thoại mới', 'name_en' => 'New phone', 'slug' => 'new-phone', 'price' => '123.45', 'quantity' => 10, 'status' => '1', 'is_featured' => '0'];
    }

    public function test_product_can_be_created_edited_and_deleted(): void
    {
        $this->get(route('admin.products.create'))->assertOk();
        $this->post(route('admin.products.store'), $this->data)->assertRedirect(route('admin.products'));
        $product = Product::where('sku', 'PHONE-1')->firstOrFail();
        $this->get(route('admin.products.edit', $product))->assertOk();
        $this->put(route('admin.products.update', $product), array_merge($this->data, ['quantity' => 20]))->assertRedirect(route('admin.products'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 20]);
        $this->delete(route('admin.products.destroy', $product))->assertRedirect(route('admin.products'));
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_product_list_pagination_and_search(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            Product::create(array_merge($this->data, ['sku' => 'SKU-'.$i, 'slug' => 'slug-'.$i]));
        }
        $this->get(route('admin.products'))
            ->assertOk()
            ->assertSee('data-confirm-title="Xóa sản phẩm"', false)
            ->assertSee('id="admin-confirm-action-modal"', false)
            ->assertSee('assets/admin/js/admin-confirm-modal.js', false)
            ->assertDontSee('return confirm(', false)
            ->assertViewHas('products', fn ($items) => $items->total() === 16 && $items->count() === 15);
        $this->get(route('admin.products', ['q' => 'SKU-16']))->assertOk()->assertViewHas('products', fn ($items) => $items->total() === 1);
    }

    public function test_invalid_or_duplicate_product_is_not_saved(): void
    {
        Product::create($this->data);
        $this->post(route('admin.products.store'), array_merge($this->data, ['price' => '-1', 'quantity' => '-1', 'category_id' => 99999]))
            ->assertSessionHasErrors(['sku', 'slug', 'price', 'quantity', 'category_id']);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_deletion_is_rejected_when_product_is_in_cart(): void
    {
        $product = Product::create($this->data);
        $cart = Cart::create(['session_token' => 'test-cart']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->from(route('admin.products'))->delete(route('admin.products.destroy', $product))->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_each_write_operation_requires_permission(): void
    {
        $role = Role::where('name', 'catalog_manager')->firstOrFail();
        $role->permissions()->sync($role->permissions()->where('name', 'products.view')->pluck('permissions.id'));
        $product = Product::create($this->data);
        $this->get(route('admin.products'))->assertOk()->assertDontSee(route('admin.products.create'), false);
        $this->get(route('admin.products.create'))->assertForbidden();
        $this->post(route('admin.products.store'), $this->data)->assertForbidden();
        $this->get(route('admin.products.edit', $product))->assertForbidden();
        $this->put(route('admin.products.update', $product), $this->data)->assertForbidden();
        $this->delete(route('admin.products.destroy', $product))->assertForbidden();
    }
}
