<?php

namespace Tests\Unit;

use App\Exceptions\MenuOperationException;
use App\Models\AdminAuditLog;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\MenuService;
use App\Services\PermissionMatrixService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class MenuServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_backfills_menu_permissions_without_detaching_existing_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $catalogManager = Role::where('name', 'catalog_manager')->firstOrFail();
        $menuPermissions = Permission::where('name', 'like', 'menus.%')->get();
        $catalogManager->permissions()->detach($menuPermissions->pluck('id'));

        $this->seed(RolePermissionSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['menus.view', 'menus.create', 'menus.update', 'menus.delete'],
            $catalogManager->permissions()->where('name', 'like', 'menus.%')->pluck('name')->all(),
        );

        $extraPermission = Permission::where('name', 'orders.view')->firstOrFail();
        $catalogManager->permissions()->attach($extraPermission);

        $this->seed(RolePermissionSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['menus.view', 'menus.create', 'menus.update', 'menus.delete'],
            $catalogManager->permissions()->where('name', 'like', 'menus.%')->pluck('name')->all(),
        );
        $this->assertTrue($catalogManager->permissions()->whereKey($extraPermission)->exists());
        $this->assertSame(4, Permission::where('name', 'like', 'menus.%')->count());

        $groups = app(PermissionMatrixService::class)->grouped();
        $this->assertTrue($groups->has('menus'));
        $this->assertCount(4, $groups['menus']['permissions']);
    }

    public function test_create_update_status_and_delete_are_authorized_audited_and_clear_cache(): void
    {
        $actor = $this->actor('admin');
        $service = app(MenuService::class);
        $cacheKey = 'menus.published.v2.header.vi';
        Cache::put($cacheKey, ['stale']);

        $menu = $service->createMenu($actor, $this->routePayload('home'));

        $this->assertFalse(Cache::has($cacheKey));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'menu.created', 'subject_id' => $menu->id]);

        Cache::put($cacheKey, ['stale']);
        $menu = $service->updateMenu($actor, $menu, [
            'name_vi' => 'Cửa hàng',
            'name_en' => 'Shop',
            'link_type' => 'route',
            'route_name' => 'shop',
        ]);
        $this->assertSame('shop', $menu->route_name);
        $this->assertFalse(Cache::has($cacheKey));

        $menu = $service->updateStatus($actor, $menu, false);
        $this->assertFalse($menu->status);

        $service->deleteMenu($actor, $menu);
        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
        $this->assertSame(
            ['menu.created', 'menu.updated', 'menu.status_updated', 'menu.deleted'],
            AdminAuditLog::orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_service_rejects_unauthorized_actor_unsafe_links_and_routes_outside_allowlist(): void
    {
        $service = app(MenuService::class);
        $customer = User::factory()->create();

        try {
            $service->createMenu($customer, $this->routePayload('home'));
            $this->fail('A customer must not create menu items.');
        } catch (MenuOperationException $exception) {
            $this->assertSame('Bạn không có quyền thực hiện thao tác với menu website.', $exception->getMessage());
        }

        $actor = $this->actor('admin');

        foreach (['javascript:alert(1)', '//evil.example/path', '/admin/users', '/%61dmin/users', '/shop/../admin/users', '/%2fadmin/users'] as $url) {
            $this->expectMenuFailure(fn () => $service->createMenu($actor, $this->urlPayload($url)));
        }

        $this->expectMenuFailure(fn () => $service->createMenu($actor, [
            ...$this->routePayload('home'),
            'route_name' => 'admin.dashboard',
        ]));
        $this->assertSame(0, Menu::count());
    }

    public function test_tree_invariants_allow_four_levels_and_block_wrong_location_fifth_level_cycle_and_parent_deletion(): void
    {
        $actor = $this->actor('admin');
        $service = app(MenuService::class);
        $root = $service->createMenu($actor, $this->containerPayload('header'));
        $child = $service->createMenu($actor, [
            ...$this->routePayload('shop'),
            'parent_id' => $root->id,
        ]);

        $levelThree = $service->createMenu($actor, [
            ...$this->routePayload('products'),
            'parent_id' => $child->id,
        ]);
        $levelFour = $service->createMenu($actor, [
            ...$this->routePayload('contact'),
            'parent_id' => $levelThree->id,
        ]);
        $this->expectMenuFailure(fn () => $service->createMenu($actor, [
            ...$this->routePayload('home'),
            'parent_id' => $levelFour->id,
        ]));
        $this->expectMenuFailure(fn () => $service->createMenu($actor, [
            ...$this->routePayload('contact'),
            'location' => 'footer',
            'parent_id' => $root->id,
        ]));
        $this->expectMenuFailure(fn () => $service->updateMenu($actor, $root, ['parent_id' => $root->id]));
        $this->expectMenuFailure(fn () => $service->updateMenu($actor, $root, ['location' => 'footer']));
        $this->expectMenuFailure(fn () => $service->deleteMenu($actor, $root));

        $this->assertDatabaseHas('menus', ['id' => $root->id]);
        $this->assertDatabaseHas('menus', ['id' => $child->id]);
    }

    public function test_reorder_requires_exact_sibling_scope_and_writes_one_audit_event(): void
    {
        $actor = $this->actor('admin');
        $service = app(MenuService::class);
        $first = $service->createMenu($actor, [...$this->routePayload('home'), 'sort_order' => 10]);
        $second = $service->createMenu($actor, [...$this->routePayload('shop'), 'sort_order' => 20]);
        $footer = $service->createMenu($actor, $this->containerPayload('footer'));
        $beforeAuditCount = AdminAuditLog::count();

        $ordered = $service->reorder($actor, 'header', null, [$second->id, $first->id]);

        $this->assertSame([$second->id, $first->id], $ordered->pluck('id')->all());
        $this->assertSame(10, $second->fresh()->sort_order);
        $this->assertSame(20, $first->fresh()->sort_order);
        $this->assertSame($beforeAuditCount + 1, AdminAuditLog::count());
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'menu.reordered', 'subject_id' => null]);

        $this->expectMenuFailure(fn () => $service->reorder($actor, 'header', null, [$first->id]));
        $this->expectMenuFailure(fn () => $service->reorder($actor, 'header', null, [$first->id, $footer->id]));
    }

    public function test_published_tree_uses_locale_active_state_parent_status_and_cache(): void
    {
        $actor = $this->actor('admin');
        $service = app(MenuService::class);
        $root = $service->createMenu($actor, $this->containerPayload('header'));
        $child = $service->createMenu($actor, [
            ...$this->routePayload('shop'),
            'parent_id' => $root->id,
            'target' => '_blank',
        ]);
        $disabledRoot = $service->createMenu($actor, [...$this->containerPayload('header'), 'name_vi' => 'Ẩn', 'name_en' => 'Hidden']);
        $service->createMenu($actor, [...$this->routePayload('contact'), 'parent_id' => $disabledRoot->id]);
        $service->updateStatus($actor, $disabledRoot, false);

        $tree = $service->published('header', 'en', 'shop', '/shop');

        $this->assertCount(1, $tree);
        $this->assertSame('Group', $tree[0]['label']);
        $this->assertTrue($tree[0]['active']);
        $this->assertSame('Shop', $tree[0]['children'][0]['label']);
        $this->assertTrue($tree[0]['children'][0]['active']);
        $this->assertSame('noopener noreferrer', $tree[0]['children'][0]['rel']);

        $child->update(['name_en' => 'Changed outside service']);
        $cached = $service->published('header', 'en', 'shop', '/shop');
        $this->assertSame('Shop', $cached[0]['children'][0]['label']);
        $service->forgetPublishedCache();
        $fresh = $service->published('header', 'en', 'shop', '/shop');
        $this->assertSame('Changed outside service', $fresh[0]['children'][0]['label']);
    }

    public function test_audit_failure_rolls_back_mutation_and_does_not_clear_published_cache(): void
    {
        $actor = $this->actor('admin');
        $cacheKey = 'menus.published.v2.header.vi';
        Cache::put($cacheKey, ['stable']);
        $this->mock(AdminAuditService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
        });

        try {
            app(MenuService::class)->createMenu($actor, $this->routePayload('home'));
            $this->fail('The audit failure must abort the menu mutation.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertSame(0, Menu::count());
        $this->assertSame(['stable'], Cache::get($cacheKey));
    }

    public function test_move_audit_failure_rolls_back_parent_order_and_cache(): void
    {
        $actor = $this->actor('admin');
        $source = Menu::create($this->containerPayload('header'));
        $subject = Menu::create([...$this->routePayload('shop'), 'parent_id' => $source->id]);
        $destination = Menu::create([...$this->containerPayload('header'), 'name_vi' => 'Đích', 'name_en' => 'Destination']);
        $cacheKey = 'menus.published.v2.header.vi';
        Cache::put($cacheKey, ['stable']);
        $this->mock(AdminAuditService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
        });

        try {
            app(MenuService::class)->moveMenu(
                $actor,
                $subject,
                $destination->id,
                'header',
                $source->id,
                'header',
            );
            $this->fail('The audit failure must roll back the branch move.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $subject->refresh();
        $this->assertSame($source->id, $subject->parent_id);
        $this->assertSame(10, $subject->sort_order);
        $this->assertSame(['stable'], Cache::get($cacheKey));
    }

    private function actor(string $roleName, bool $seed = true): User
    {
        if ($seed) {
            $this->seed(RolePermissionSeeder::class);
        }

        $actor = User::factory()->create();
        $actor->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $actor;
    }

    /** @return array<string, mixed> */
    private function routePayload(string $routeName): array
    {
        return [
            'name_vi' => match ($routeName) {
                'home' => 'Trang chủ',
                'shop' => 'Cửa hàng',
                'products' => 'Sản phẩm',
                'contact' => 'Liên hệ',
                default => 'Menu',
            },
            'name_en' => ucfirst($routeName),
            'link_type' => 'route',
            'route_name' => $routeName,
            'target' => '_self',
            'location' => 'header',
            'sort_order' => 10,
            'status' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function urlPayload(string $url): array
    {
        return [
            'name_vi' => 'Liên kết',
            'name_en' => 'Link',
            'link_type' => 'url',
            'url' => $url,
            'target' => '_self',
            'location' => 'header',
            'sort_order' => 10,
            'status' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function containerPayload(string $location): array
    {
        return [
            'name_vi' => 'Nhóm',
            'name_en' => 'Group',
            'link_type' => 'none',
            'target' => '_self',
            'location' => $location,
            'sort_order' => 10,
            'status' => true,
        ];
    }

    private function expectMenuFailure(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a menu operation failure.');
        } catch (MenuOperationException) {
            $this->addToAssertionCount(1);
        }
    }
}
