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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('product_images');
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'catalog_manager')->firstOrFail());
        $this->actingAs($user);
        $category = Category::create(['name_vi' => 'Test', 'name_en' => 'Test', 'slug' => 'test']);
        $this->data = ['category_id' => $category->id, 'name_vi' => 'Test', 'name_en' => 'Test', 'sku' => 'TEST', 'slug' => 'test', 'price' => '10.00', 'quantity' => 2, 'status' => '1', 'is_featured' => '0'];
    }

    private function upload(): UploadedFile
    {
        // A real tiny PNG, independent of the GD extension.
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    public function test_upload_primary_metadata_image_response_and_delete(): void
    {
        $this->post(route('admin.products.store'), array_merge($this->data, [
            'images' => [$this->upload(), $this->upload()], 'primary_image' => 'new:1', 'new_alt' => ['First', 'Second'],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.products'));
        $product = Product::firstOrFail();
        $images = $product->images()->orderBy('id')->get();
        $this->assertCount(2, $images);
        $this->assertFalse($images[0]->is_primary);
        $this->assertTrue($images[1]->is_primary);
        Storage::disk('product_images')->assertExists($images[0]->image_url);
        $this->get(route('product-images.show', $images[0]))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->put(route('admin.products.update', $product), array_merge($this->data, [
            'delete_images' => [$images[1]->id],
            'image_meta' => [$images[0]->id => ['alt_text' => 'Updated', 'sort_order' => 7]],
        ]))->assertSessionHasNoErrors();
        Storage::disk('product_images')->assertMissing($images[1]->image_url);
        $this->assertTrue($images[0]->fresh()->is_primary);
        $this->assertSame('Updated', $images[0]->fresh()->alt_text);
        $this->delete(route('admin.products.destroy', $product))->assertRedirect(route('admin.products'));
        Storage::disk('product_images')->assertMissing($images[0]->image_url);
    }

    public function test_bad_files_and_foreign_image_ids_are_rejected(): void
    {
        $this->post(route('admin.products.store'), array_merge($this->data, [
            'images' => [UploadedFile::fake()->create('evil.php', 1, 'application/x-php')],
        ]))->assertSessionHasErrors('images.0');
        $this->post(route('admin.products.store'), array_merge($this->data, [
            'images' => [$this->upload()->size(2049)],
        ]))->assertSessionHasErrors('images.0');
        $product = Product::create($this->data);
        $this->put(route('admin.products.update', $product), array_merge($this->data, [
            'delete_images' => [99999], 'primary_image' => 'existing:99999',
            'image_meta' => [99999 => ['alt_text' => 'tamper', 'sort_order' => 0]],
        ]))->assertSessionHasErrors(['delete_images.0', 'primary_image', 'image_meta']);
        Storage::disk('product_images')->assertDirectoryEmpty('/');
    }

    public function test_blocked_product_deletion_preserves_image_files(): void
    {
        $this->post(route('admin.products.store'), array_merge($this->data, ['images' => [$this->upload()]]))->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $image = $product->images()->firstOrFail();
        $cart = Cart::create(['session_token' => 'upload-test']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->delete(route('admin.products.destroy', $product))->assertSessionHas('error');
        Storage::disk('product_images')->assertExists($image->image_url);
        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
    }

    public function test_upload_requires_permission_and_hidden_product_image_is_not_public(): void
    {
        $this->post(route('admin.products.store'), array_merge($this->data, ['status' => '0', 'images' => [$this->upload()]]))->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $image = $product->images()->firstOrFail();
        $this->get(route('product-images.show', $image))->assertOk();
        $role = Role::where('name', 'catalog_manager')->firstOrFail();
        $role->permissions()->sync($role->permissions()->where('name', 'products.view')->pluck('permissions.id'));
        $this->put(route('admin.products.update', $product), array_merge($this->data, ['images' => [$this->upload()]]))->assertForbidden();
        $this->assertDatabaseCount('product_images', 1);
        auth()->logout();
        $this->get(route('product-images.show', $image))->assertNotFound();
    }
}
