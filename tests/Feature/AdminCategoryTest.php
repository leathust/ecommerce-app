<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
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
        return array_merge(['name_vi' => 'Điện thoại', 'name_en' => 'Phones', 'slug' => 'phones', 'parent_id' => null, 'image_url' => null, 'sort_order' => 0, 'status' => '1'], $overrides);
    }

    public function test_crud_and_children_become_root_on_delete(): void
    {
        $this->get(route('admin.categories.create'))->assertOk();
        $this->post(route('admin.categories.store'), $this->payload(['slug' => '']))->assertRedirect(route('admin.categories.index'));
        $category = Category::firstOrFail();
        $this->assertSame('dien-thoai', $category->slug);
        $child = Category::create($this->payload(['slug' => 'child', 'parent_id' => $category->id]));
        $this->get(route('admin.categories.edit', $category))->assertOk();
        $this->put(route('admin.categories.update', $category), $this->payload(['name_vi' => 'Tên mới']))->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name_vi' => 'Tên mới']);
        $this->delete(route('admin.categories.destroy', $category))->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_rejects_self_parent_descendant_parent_and_duplicate_slug(): void
    {
        $parent = Category::create($this->payload());
        $child = Category::create($this->payload(['slug' => 'child', 'parent_id' => $parent->id]));
        $this->put(route('admin.categories.update', $parent), $this->payload(['parent_id' => $parent->id]))->assertSessionHasErrors('parent_id');
        $this->put(route('admin.categories.update', $parent), $this->payload(['parent_id' => $child->id]))->assertSessionHasErrors('parent_id');
        $this->post(route('admin.categories.store'), $this->payload())->assertSessionHasErrors('slug');
        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_cannot_delete_category_with_products(): void
    {
        $category = Category::create($this->payload());
        Product::create(['category_id' => $category->id, 'sku' => 'TEST', 'slug' => 'test', 'name_vi' => 'Test', 'name_en' => 'Test', 'price' => '1.00', 'quantity' => 1]);
        $this->from(route('admin.categories.index'))->delete(route('admin.categories.destroy', $category))->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_pagination_search_and_permissions(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            Category::create($this->payload(['slug' => 'category-'.$i]));
        }
        $this->get(route('admin.categories.index'))->assertOk()->assertViewHas('categories', fn ($items) => $items->total() === 16 && $items->count() === 15);
        $this->get(route('admin.categories.index', ['q' => 'category-16']))->assertOk()->assertViewHas('categories', fn ($items) => $items->total() === 1);
        $role = Role::where('name', 'catalog_manager')->firstOrFail();
        $role->permissions()->sync($role->permissions()->where('name', 'categories.view')->pluck('permissions.id'));
        $category = Category::firstOrFail();
        $this->get(route('admin.categories.index'))->assertOk()->assertDontSee(route('admin.categories.create'), false);
        $this->get(route('admin.categories.create'))->assertForbidden();
        $this->post(route('admin.categories.store'), $this->payload())->assertForbidden();
        $this->get(route('admin.categories.edit', $category))->assertForbidden();
        $this->put(route('admin.categories.update', $category), $this->payload())->assertForbidden();
        $this->delete(route('admin.categories.destroy', $category))->assertForbidden();
    }
}
