<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('catalog_images');
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'catalog_manager')->firstOrFail());
        $this->actingAs($user);
    }

    private function data(string $slug): array
    {
        return ['name_vi' => 'Test', 'name_en' => 'Test', 'slug' => $slug, 'status' => '1', 'sort_order' => 0];
    }

    private function upload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    public function test_upload_replace_remove_and_delete_for_both_modules(): void
    {
        foreach (['categories' => Category::class, 'brands' => Brand::class] as $type => $model) {
            $data = $this->data($type);
            $this->post(route('admin.'.$type.'.store'), array_merge($data, ['image' => $this->upload()]))->assertSessionHasNoErrors();
            $record = $model::firstOrFail();
            $old = $record->image_url;
            Storage::disk('catalog_images')->assertExists($old);
            $this->get($record->image_src)->assertOk()->assertHeader('Content-Type', 'image/png');
            $this->get(route('admin.'.$type.'.edit', $record))->assertOk();
            $this->put(route('admin.'.$type.'.update', $record), array_merge($data, ['image' => $this->upload()]))->assertSessionHasNoErrors();
            Storage::disk('catalog_images')->assertMissing($old);
            $newPath = $record->fresh()->image_url;
            Storage::disk('catalog_images')->assertExists($newPath);
            $this->put(route('admin.'.$type.'.update', $record), array_merge($data, ['remove_image' => '1']))->assertSessionHasNoErrors();
            $this->assertNull($record->fresh()->image_url);
            Storage::disk('catalog_images')->assertMissing($newPath);
            $this->put(route('admin.'.$type.'.update', $record), array_merge($data, ['image' => $this->upload()]))->assertSessionHasNoErrors();
            $lastPath = $record->fresh()->image_url;
            $this->delete(route('admin.'.$type.'.destroy', $record))->assertRedirect();
            Storage::disk('catalog_images')->assertMissing($lastPath);
        }
    }

    public function test_invalid_images_and_unauthorized_upload_are_rejected(): void
    {
        foreach (['categories', 'brands'] as $type) {
            $this->post(route('admin.'.$type.'.store'), array_merge($this->data($type), ['image' => UploadedFile::fake()->create('evil.php', 1, 'application/x-php')]))->assertSessionHasErrors('image');
            $this->post(route('admin.'.$type.'.store'), array_merge($this->data($type), ['image' => $this->upload()->size(2049)]))->assertSessionHasErrors('image');
        }
        $role = Role::where('name', 'catalog_manager')->firstOrFail();
        $role->permissions()->detach();
        $this->post(route('admin.brands.store'), array_merge($this->data('denied'), ['image' => $this->upload()]))->assertForbidden();
        Storage::disk('catalog_images')->assertDirectoryEmpty('/');
    }

    public function test_category_blocked_deletion_preserves_image_and_external_url_can_replace_upload(): void
    {
        $data = $this->data('category');
        $this->post(route('admin.categories.store'), array_merge($data, ['image' => $this->upload()]))->assertSessionHasNoErrors();
        $category = Category::firstOrFail();
        $path = $category->image_url;
        Product::create(['category_id' => $category->id, 'name_vi' => 'Test', 'name_en' => 'Test', 'slug' => 'test', 'sku' => 'TEST', 'price' => '1.00']);
        $this->delete(route('admin.categories.destroy', $category))->assertSessionHas('error');
        Storage::disk('catalog_images')->assertExists($path);
        $this->put(route('admin.categories.update', $category), array_merge($data, ['image_url' => 'https://example.com/logo.png']))->assertSessionHasNoErrors();
        Storage::disk('catalog_images')->assertMissing($path);
        $this->assertSame('https://example.com/logo.png', $category->fresh()->image_src);
    }
}
