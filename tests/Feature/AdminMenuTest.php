<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_routes_have_specific_permissions_and_navigation_follows_view_permission(): void
    {
        $expected = [
            'admin.menus.index' => 'permission:menus.view',
            'admin.menus.create' => 'permission:menus.create',
            'admin.menus.store' => 'permission:menus.create',
            'admin.menus.move.form' => 'permission:menus.update',
            'admin.menus.move' => 'permission:menus.update',
            'admin.menus.edit' => 'permission:menus.update',
            'admin.menus.update' => 'permission:menus.update',
            'admin.menus.reorder' => 'permission:menus.update',
            'admin.menus.status.update' => 'permission:menus.update',
            'admin.menus.destroy' => 'permission:menus.delete',
        ];

        foreach ($expected as $name => $middleware) {
            $this->assertContains($middleware, Route::getRoutes()->getByName($name)->gatherMiddleware());
        }

        $viewer = $this->userWithPermissions('menu_viewer', ['dashboard.view', 'menus.view']);
        $denied = $this->userWithRole('order_manager');

        $this->actingAs($viewer)->get(route('admin.dashboard'))
            ->assertOk()->assertSee(route('admin.menus.index'), false);
        $this->actingAs($denied)->get(route('admin.dashboard'))
            ->assertOk()->assertDontSee(route('admin.menus.index'), false);
    }

    public function test_move_modal_is_rendered_at_body_level_above_the_bootstrap_backdrop(): void
    {
        $actor = $this->userWithRole('admin');
        $this->menu(['name_vi' => 'Menu di chuyển', 'name_en' => 'Move menu']);

        $html = $this->actingAs($actor)->get(route('admin.menus.index'))->assertOk()->getContent();
        $mainClose = strpos($html, '</main>');
        $modal = strpos($html, 'id="menu-move-modal"');
        $bootstrapScript = strpos($html, 'bootstrap-5.3.8.bundle.min.js');

        $this->assertNotFalse($mainClose);
        $this->assertNotFalse($modal);
        $this->assertNotFalse($bootstrapScript);
        $this->assertGreaterThan($mainClose, $modal, 'Move modal must not be nested in the main stacking context.');
        $this->assertLessThan($bootstrapScript, $modal, 'Move modal must exist before Bootstrap initializes.');
        $this->assertSame(1, substr_count($html, 'id="menu-move-modal"'));
    }

    public function test_tree_list_filters_searches_and_paginates_roots_without_splitting_children(): void
    {
        $actor = $this->userWithRole('admin');
        $firstRoot = null;

        foreach (range(1, 11) as $index) {
            $root = $this->menu([
                'name_vi' => 'Header '.$index,
                'name_en' => 'Header '.$index,
                'sort_order' => $index * 10,
                'status' => true,
            ]);
            $firstRoot ??= $root;
        }
        $child = $this->menu([
            'parent_id' => $firstRoot->id,
            'name_vi' => 'Mục con cần tìm',
            'name_en' => 'Search child',
            'sort_order' => 10,
        ]);
        $levelThree = $this->menu([
            'parent_id' => $child->id,
            'name_vi' => 'Nhánh cấp ba',
            'name_en' => 'Level three',
            'sort_order' => 10,
        ]);
        $levelFour = $this->menu([
            'parent_id' => $levelThree->id,
            'name_vi' => 'Đích sâu cấp bốn',
            'name_en' => 'Deep level four',
            'sort_order' => 10,
            'status' => false,
        ]);
        $this->menu(['location' => 'footer', 'name_vi' => 'Footer only', 'name_en' => 'Footer only']);

        $this->actingAs($actor)->get(route('admin.menus.index', ['location' => 'header']))
            ->assertOk()
            ->assertSee('Header 1')
            ->assertSee('Mục con cần tìm')
            ->assertSee('Đích sâu cấp bốn')
            ->assertSee('data-menu-level="3"', false)
            ->assertSee('Cấp 4')
            ->assertSee('page=2', false)
            ->assertDontSee('<strong>Footer only</strong>', false);

        $this->actingAs($actor)->get(route('admin.menus.index', [
            'location' => 'header', 'q' => 'Mục con cần tìm',
        ]))->assertOk()
            ->assertSee('Header 1')
            ->assertSee($child->name_vi);

        $this->actingAs($actor)->get(route('admin.menus.index', [
            'location' => 'header', 'q' => $levelFour->name_vi,
        ]))->assertOk()
            ->assertSee('Header 1')
            ->assertSee($levelThree->name_vi)
            ->assertSee($levelFour->name_vi)
            ->assertSee('Header 1 / Mục con cần tìm / Nhánh cấp ba / Đích sâu cấp bốn');

        $this->actingAs($actor)->get(route('admin.menus.index', [
            'location' => 'header', 'status' => 'inactive',
        ]))->assertOk()
            ->assertSee('Header 1')
            ->assertSee($levelThree->name_vi)
            ->assertSee($levelFour->name_vi);

        $this->actingAs($actor)->get(route('admin.menus.index', [
            'location' => 'header', 'q' => 'Header', 'status' => 'active',
        ]))->assertOk()
            ->assertSee('page=2', false)
            ->assertSee('q=Header', false)
            ->assertSee('status=active', false);

        $this->actingAs($actor)->get(route('admin.menus.index', ['location' => 'footer']))
            ->assertOk()->assertSee('Footer only')->assertDontSee('<strong>Header 1</strong>', false);
    }

    public function test_admin_can_create_update_and_delete_menu_with_audit(): void
    {
        $actor = $this->userWithRole('admin');
        $parent = $this->menu(['name_vi' => 'Nhóm cha', 'name_en' => 'Parent group', 'link_type' => 'none', 'url' => null]);

        $this->actingAs($actor)->post(route('admin.menus.store'), [
            'parent_id' => $parent->id,
            'name_vi' => 'Liên hệ mới',
            'name_en' => 'New contact',
            'link_type' => 'route',
            'route_name' => 'contact',
            'url' => 'https://ignored.example',
            'target' => '_self',
            'location' => 'header',
            'sort_order' => 20,
            'status' => '1',
            'unexpected' => 'must-not-be-persisted',
        ])->assertRedirect(route('admin.menus.index', ['location' => 'header']));

        $menu = Menu::query()->where('name_vi', 'Liên hệ mới')->sole();
        $this->assertSame($parent->id, $menu->parent_id);
        $this->assertSame('contact', $menu->route_name);
        $this->assertNull($menu->url);

        $this->actingAs($actor)->put(route('admin.menus.update', $menu), [
            'parent_id' => $parent->id,
            'name_vi' => 'Trung tâm hỗ trợ',
            'name_en' => 'Support center',
            'link_type' => 'url',
            'route_name' => 'home',
            'url' => '/help',
            'target' => '_blank',
            'location' => 'header',
            'sort_order' => 30,
            'status' => '1',
        ])->assertRedirect(route('admin.menus.index', ['location' => 'header']));

        $menu->refresh();
        $this->assertSame($parent->id, $menu->parent_id);
        $this->assertSame('/help', $menu->url);
        $this->assertNull($menu->route_name);
        $this->assertSame('_blank', $menu->target);

        $this->actingAs($actor)->delete(route('admin.menus.destroy', $menu))
            ->assertRedirect(route('admin.menus.index', ['location' => 'header']));

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
        $this->assertSame(
            ['menu.created', 'menu.updated', 'menu.deleted'],
            AdminAuditLog::query()->where('subject_type', Menu::class)->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_form_validation_allows_four_levels_and_rejects_cross_location_fifth_level_and_unsafe_link(): void
    {
        $actor = $this->userWithRole('admin');
        $headerRoot = $this->menu();
        $footerRoot = $this->menu(['location' => 'footer', 'name_vi' => 'Footer', 'name_en' => 'Footer']);
        $child = $this->menu(['parent_id' => $headerRoot->id, 'name_vi' => 'Child', 'name_en' => 'Child']);
        $base = [
            'name_vi' => 'Menu test', 'name_en' => 'Test menu', 'link_type' => 'url',
            'url' => '/contact', 'target' => '_self', 'location' => 'header',
            'sort_order' => 0, 'status' => '1',
        ];

        $this->actingAs($actor)->post(route('admin.menus.store'), $base + ['parent_id' => $footerRoot->id])
            ->assertSessionHasErrors('parent_id');
        $this->actingAs($actor)->post(route('admin.menus.store'), array_replace($base, [
            'name_vi' => 'Cấp ba', 'name_en' => 'Level three', 'parent_id' => $child->id,
        ]))->assertSessionHasNoErrors();
        $levelThree = Menu::where('name_vi', 'Cấp ba')->sole();
        $this->actingAs($actor)->post(route('admin.menus.store'), array_replace($base, [
            'name_vi' => 'Cấp bốn', 'name_en' => 'Level four', 'parent_id' => $levelThree->id,
        ]))->assertSessionHasNoErrors();
        $levelFour = Menu::where('name_vi', 'Cấp bốn')->sole();
        $this->actingAs($actor)->post(route('admin.menus.store'), array_replace($base, [
            'name_vi' => 'Cấp năm', 'name_en' => 'Level five', 'parent_id' => $levelFour->id,
        ]))->assertSessionHasErrors('parent_id');
        $this->actingAs($actor)->post(route('admin.menus.store'), array_replace($base, [
            'parent_id' => null, 'url' => 'javascript:alert(1)',
        ]))->assertSessionHasErrors('menu');

        $this->assertDatabaseMissing('menus', ['name_vi' => 'Menu test']);
    }

    public function test_parent_options_expose_valid_levels_with_breadcrumb_and_exclude_subtree_or_level_four(): void
    {
        $actor = $this->userWithRole('admin');
        $root = $this->menu(['name_vi' => 'Root option', 'name_en' => 'Root option']);
        $child = $this->menu(['parent_id' => $root->id, 'name_vi' => 'Child option', 'name_en' => 'Child option']);
        $levelThree = $this->menu(['parent_id' => $child->id, 'name_vi' => 'Third option', 'name_en' => 'Third option']);
        $levelFour = $this->menu(['parent_id' => $levelThree->id, 'name_vi' => 'Fourth option', 'name_en' => 'Fourth option']);

        $this->actingAs($actor)->get(route('admin.menus.create'))
            ->assertOk()
            ->assertSee('value="'.$root->id.'" data-location="header" data-depth="1"', false)
            ->assertSee('value="'.$child->id.'" data-location="header" data-depth="2"', false)
            ->assertSee('value="'.$levelThree->id.'" data-location="header" data-depth="3"', false)
            ->assertSee('Root option / Child option / Third option')
            ->assertDontSee('value="'.$levelFour->id.'" data-location=', false);

        $this->actingAs($actor)->get(route('admin.menus.edit', $root))
            ->assertOk()
            ->assertDontSee('value="'.$root->id.'" data-location=', false)
            ->assertDontSee('value="'.$child->id.'" data-location=', false)
            ->assertDontSee('value="'.$levelThree->id.'" data-location=', false)
            ->assertSee('Vị trí và menu cha được khóa');

        $this->actingAs($actor)->put(route('admin.menus.update', $root), [
            'parent_id' => $child->id,
            'name_vi' => $root->name_vi,
            'name_en' => $root->name_en,
            'link_type' => $root->link_type,
            'url' => $root->url,
            'target' => '_self',
            'location' => 'header',
            'sort_order' => 0,
            'status' => '1',
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_delete_guard_keeps_parent_with_children_and_shows_clear_error(): void
    {
        $actor = $this->userWithRole('admin');
        $parent = $this->menu(['name_vi' => 'Không được xóa', 'name_en' => 'Cannot delete']);
        $this->menu(['parent_id' => $parent->id, 'name_vi' => 'Con', 'name_en' => 'Child']);

        $this->actingAs($actor)->from(route('admin.menus.index'))
            ->delete(route('admin.menus.destroy', $parent))
            ->assertRedirect(route('admin.menus.index'))
            ->assertSessionHas('error', 'Không thể xóa menu đang có mục con. Hãy xử lý các mục con trước.');

        $this->assertDatabaseHas('menus', ['id' => $parent->id]);
    }

    public function test_permissions_and_route_model_binding_return_403_or_404(): void
    {
        $viewer = $this->userWithPermissions('menu_read_only', ['menus.view']);
        $menu = $this->menu();

        $this->actingAs($viewer)->get(route('admin.menus.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.menus.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.menus.store'), [])->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.menus.edit', $menu))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.menus.move.form', $menu))->assertForbidden();
        $this->actingAs($viewer)->patch(route('admin.menus.move', $menu), [])->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.menus.update', $menu), [])->assertForbidden();
        $this->actingAs($viewer)->patch(route('admin.menus.status.update', $menu), ['status' => 0])->assertForbidden();
        $this->actingAs($viewer)->patch(route('admin.menus.reorder'), [
            'location' => 'header', 'ordered_ids' => [$menu->id],
        ])->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.menus.destroy', $menu))->assertForbidden();

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get('/admin/menus/999999/edit')->assertNotFound();
        $this->actingAs($admin)->get('/admin/menus/999999/move')->assertNotFound();
        $this->actingAs($admin)->put('/admin/menus/999999', [])->assertNotFound();
        $this->actingAs($admin)->delete('/admin/menus/999999')->assertNotFound();
    }

    public function test_ajax_status_toggle_updates_audit_and_published_cache_with_html_fallback(): void
    {
        $actor = $this->userWithRole('admin');
        $menu = $this->menu(['name_vi' => 'Trạng thái nhanh', 'name_en' => 'Quick status']);
        $service = app(\App\Services\MenuService::class);
        $this->assertCount(1, $service->published('header', 'vi'));

        $this->actingAs($actor)->patchJson(route('admin.menus.status.update', $menu), ['status' => false])
            ->assertOk()
            ->assertJsonPath('menu.id', $menu->id)
            ->assertJsonPath('menu.status', false);

        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'status' => false]);
        $this->assertSame([], $service->published('header', 'vi'));
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'menu.status_updated', 'subject_id' => $menu->id,
        ]);

        $this->actingAs($actor)->from(route('admin.menus.index'))
            ->patch(route('admin.menus.status.update', $menu), ['status' => '1'])
            ->assertRedirect(route('admin.menus.index'))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'status' => true]);
    }

    public function test_ajax_reorder_validates_complete_scope_updates_orders_and_supports_fallback(): void
    {
        $actor = $this->userWithRole('admin');
        $first = $this->menu(['name_vi' => 'Một', 'name_en' => 'One', 'sort_order' => 10]);
        $second = $this->menu(['name_vi' => 'Hai', 'name_en' => 'Two', 'sort_order' => 20]);
        $third = $this->menu(['name_vi' => 'Ba', 'name_en' => 'Three', 'sort_order' => 30]);

        $this->actingAs($actor)->patchJson(route('admin.menus.reorder'), [
            'location' => 'header',
            'parent_id' => null,
            'ordered_ids' => [$third->id, $first->id, $second->id],
        ])->assertOk()
            ->assertJsonPath('items.0.id', $third->id)
            ->assertJsonPath('items.0.sort_order', 10)
            ->assertJsonPath('items.2.id', $second->id);

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            Menu::query()->roots()->ordered()->pluck('id')->all(),
        );
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'menu.reordered']);

        $this->actingAs($actor)->patchJson(route('admin.menus.reorder'), [
            'location' => 'header', 'ordered_ids' => [$first->id, $second->id],
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Danh sách sắp xếp không khớp với các menu cùng cấp.');

        $this->actingAs($actor)->from(route('admin.menus.index'))
            ->patch(route('admin.menus.reorder'), [
                'location' => 'header',
                'ordered_ids' => [$second->id, $first->id, $third->id],
            ])->assertRedirect(route('admin.menus.index'))->assertSessionHas('success');
        $this->assertSame(
            [$second->id, $first->id, $third->id],
            Menu::query()->roots()->ordered()->pluck('id')->all(),
        );

        $parent = $this->menu(['name_vi' => 'Parent sâu', 'name_en' => 'Deep parent']);
        $deepFirst = $this->menu(['parent_id' => $parent->id, 'name_vi' => 'Con A', 'name_en' => 'Child A', 'sort_order' => 10]);
        $deepSecond = $this->menu(['parent_id' => $parent->id, 'name_vi' => 'Con B', 'name_en' => 'Child B', 'sort_order' => 20]);

        $this->actingAs($actor)->patchJson(route('admin.menus.reorder'), [
            'location' => 'header',
            'parent_id' => $parent->id,
            'ordered_ids' => [$deepSecond->id, $deepFirst->id],
        ])->assertOk()->assertJsonPath('items.0.id', $deepSecond->id);
        $this->assertSame([$deepSecond->id, $deepFirst->id], $parent->children()->ordered()->pluck('id')->all());
    }

    public function test_branch_move_is_atomic_audited_sorted_last_and_has_html_fallback(): void
    {
        $actor = $this->userWithRole('admin');
        $source = $this->menu(['name_vi' => 'Nguồn', 'name_en' => 'Source']);
        $branch = $this->menu(['parent_id' => $source->id, 'name_vi' => 'Nhánh', 'name_en' => 'Branch']);
        $descendant = $this->menu(['parent_id' => $branch->id, 'name_vi' => 'Hậu duệ', 'name_en' => 'Descendant']);
        $destination = $this->menu(['name_vi' => 'Đích', 'name_en' => 'Destination']);
        $this->menu(['parent_id' => $destination->id, 'name_vi' => 'Có sẵn', 'name_en' => 'Existing', 'sort_order' => 30]);

        $this->actingAs($actor)->get(route('admin.menus.move.form', $branch))
            ->assertOk()
            ->assertSee('Di chuyển nhánh menu')
            ->assertSee($destination->name_vi)
            ->assertDontSee('value="'.$descendant->id.'" data-location=', false);

        $this->actingAs($actor)->patchJson(route('admin.menus.move', $branch), [
            'source_parent_id' => $source->id,
            'source_location' => 'header',
            'parent_id' => $destination->id,
            'location' => 'header',
        ])->assertOk()
            ->assertJsonPath('menu.parent_id', $destination->id)
            ->assertJsonPath('menu.sort_order', 40);

        $this->assertSame($destination->id, $branch->fresh()->parent_id);
        $this->assertSame($branch->id, $descendant->fresh()->parent_id);
        $log = AdminAuditLog::where('action', 'menu.moved')->where('subject_id', $branch->id)->sole();
        $this->assertSame($source->id, $log->before_values['menu']['parent_id']);
        $this->assertSame($destination->id, $log->after_values['menu']['parent_id']);
        $this->assertSame(2, $log->after_values['menu']['depth']);

        $this->actingAs($actor)->patch(route('admin.menus.move', $branch), [
            'source_parent_id' => $destination->id,
            'source_location' => 'header',
            'parent_id' => null,
            'location' => 'header',
        ])->assertRedirect(route('admin.menus.index', ['location' => 'header']))->assertSessionHas('success');
        $this->assertNull($branch->fresh()->parent_id);
    }

    public function test_move_rejects_cycle_overflow_cross_location_branch_and_stale_payload_but_allows_leaf_location_change(): void
    {
        $actor = $this->userWithRole('admin');
        $root = $this->menu(['name_vi' => 'Root', 'name_en' => 'Root']);
        $branch = $this->menu(['parent_id' => $root->id, 'name_vi' => 'Branch', 'name_en' => 'Branch']);
        $branchLeaf = $this->menu(['parent_id' => $branch->id, 'name_vi' => 'Branch leaf', 'name_en' => 'Branch leaf']);
        $targetRoot = $this->menu(['name_vi' => 'Target', 'name_en' => 'Target']);
        $targetTwo = $this->menu(['parent_id' => $targetRoot->id, 'name_vi' => 'Target 2', 'name_en' => 'Target 2']);
        $targetThree = $this->menu(['parent_id' => $targetTwo->id, 'name_vi' => 'Target 3', 'name_en' => 'Target 3']);
        $footerRoot = $this->menu(['location' => 'footer', 'name_vi' => 'Footer', 'name_en' => 'Footer']);

        $base = ['source_parent_id' => $root->id, 'source_location' => 'header', 'location' => 'header'];
        $this->actingAs($actor)->patchJson(route('admin.menus.move', $root), [
            'source_parent_id' => null, 'source_location' => 'header', 'parent_id' => $branch->id, 'location' => 'header',
        ])->assertUnprocessable();
        $this->actingAs($actor)->patchJson(route('admin.menus.move', $branch), $base + ['parent_id' => $targetThree->id])
            ->assertUnprocessable();
        $this->actingAs($actor)->patchJson(route('admin.menus.move', $branch), array_replace($base, [
            'parent_id' => $footerRoot->id, 'location' => 'footer',
        ]))->assertUnprocessable();
        $this->actingAs($actor)->patchJson(route('admin.menus.move', $branch), array_replace($base, [
            'source_parent_id' => $targetRoot->id, 'parent_id' => $targetRoot->id,
        ]))->assertUnprocessable()->assertJsonPath('message', 'Vị trí menu đã thay đổi bởi thao tác khác. Hãy tải lại trang trước khi di chuyển.');

        $this->actingAs($actor)->patchJson(route('admin.menus.move', $branchLeaf), [
            'source_parent_id' => $branch->id,
            'source_location' => 'header',
            'parent_id' => $footerRoot->id,
            'location' => 'footer',
        ])->assertOk()->assertJsonPath('menu.location', 'footer');
        $this->assertSame($footerRoot->id, $branchLeaf->fresh()->parent_id);
        $this->assertSame('footer', $branchLeaf->fresh()->location);
    }

    /** @param array<string, mixed> $attributes */
    private function menu(array $attributes = []): Menu
    {
        return Menu::query()->create(array_replace([
            'parent_id' => null,
            'name_vi' => 'Trang chủ',
            'name_en' => 'Home',
            'link_type' => 'url',
            'route_name' => null,
            'url' => '/',
            'target' => '_self',
            'location' => 'header',
            'sort_order' => 0,
            'status' => true,
        ], $attributes));
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }

    /** @param array<int, string> $permissions */
    private function userWithPermissions(string $roleName, array $permissions): User
    {
        $role = Role::query()->create(['name' => $roleName, 'is_admin' => true, 'is_system' => false]);
        $role->permissions()->sync(Permission::query()->whereIn('name', $permissions)->pluck('id'));

        return $this->userWithRole($roleName);
    }
}
