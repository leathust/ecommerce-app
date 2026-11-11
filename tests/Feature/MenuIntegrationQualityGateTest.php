<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\MenuService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MenuIntegrationQualityGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->app->setLocale('vi');
        config()->set('menus.fallback_enabled', false);
        Cache::flush();
    }

    public function test_default_permission_matrix_and_immediate_revocation_are_enforced(): void
    {
        $menuPermissions = ['menus.view', 'menus.create', 'menus.update', 'menus.delete'];

        foreach (['super_admin', 'admin', 'catalog_manager'] as $roleName) {
            $this->assertEqualsCanonicalizing(
                $menuPermissions,
                Role::query()->where('name', $roleName)->firstOrFail()
                    ->permissions()->whereIn('name', $menuPermissions)->pluck('name')->all(),
            );
        }

        foreach (['order_manager', 'customer_support'] as $roleName) {
            $this->assertSame(0, Role::query()->where('name', $roleName)->firstOrFail()
                ->permissions()->whereIn('name', $menuPermissions)->count());
        }

        $actor = $this->actor('catalog_manager');
        $this->actingAs($actor)->get(route('admin.menus.index'))->assertOk();

        $actor->roles()->firstOrFail()->permissions()->detach(
            Permission::query()->where('name', 'menus.view')->firstOrFail(),
        );

        $this->actingAs($actor)->get(route('admin.menus.index'))->assertForbidden();
        $this->actingAs($actor)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.menus.index'), false);
    }

    public function test_admin_mutations_invalidate_cache_and_are_visible_on_storefront_immediately(): void
    {
        $actor = $this->actor('admin');
        $payload = $this->payload('Menu quality gate');

        $this->actingAs($actor)->post(route('admin.menus.store'), $payload)->assertRedirect();
        $menu = Menu::query()->where('name_vi', 'Menu quality gate')->sole();
        $this->get(route('home'))->assertOk()->assertSee('Menu quality gate');

        $this->actingAs($actor)->put(route('admin.menus.update', $menu), [
            ...$payload,
            'name_vi' => 'Menu đã cập nhật',
            'name_en' => 'Updated quality menu',
        ])->assertRedirect();
        $this->get(route('home'))->assertOk()
            ->assertSee('Menu đã cập nhật')
            ->assertDontSee('Menu quality gate');

        $this->actingAs($actor)->patch(route('admin.menus.status.update', $menu), ['status' => false])
            ->assertRedirect();
        $this->get(route('home'))->assertOk()->assertDontSee('Menu đã cập nhật');

        $this->assertSame(
            ['menu.created', 'menu.updated', 'menu.status_updated'],
            AdminAuditLog::query()->where('subject_type', Menu::class)->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_mass_assignment_route_allowlist_and_unsafe_admin_urls_are_blocked(): void
    {
        $model = new Menu();
        $this->assertEqualsCanonicalizing([
            'parent_id', 'name_vi', 'name_en', 'link_type', 'route_name', 'url',
            'target', 'location', 'sort_order', 'status',
        ], $model->getFillable());
        $this->assertNotContains('id', $model->getFillable());
        $this->assertNotContains('created_at', $model->getFillable());
        $this->assertNotContains('updated_at', $model->getFillable());

        $menu = Menu::query()->create([
            ...$this->payload('Mass assignment safe'),
            'id' => 999999,
            'unexpected_admin_flag' => true,
        ]);
        $this->assertNotSame(999999, $menu->id);
        $this->assertArrayNotHasKey('unexpected_admin_flag', $menu->getAttributes());
        $menu->delete();

        $actor = $this->actor('admin');
        $this->actingAs($actor)->post(route('admin.menus.store'), [
            ...$this->payload('Admin route leak'),
            'route_name' => 'admin.dashboard',
        ])->assertSessionHasErrors('route_name');
        $this->actingAs($actor)->post(route('admin.menus.store'), [
            ...$this->payload('Admin URL leak'),
            'link_type' => 'url',
            'route_name' => null,
            'url' => '/admin/users',
        ])->assertSessionHasErrors('menu');
        $this->actingAs($actor)->post(route('admin.menus.store'), [
            ...$this->payload('Script URL leak'),
            'link_type' => 'url',
            'route_name' => null,
            'url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('menu');

        $this->assertSame(0, Menu::count());
    }

    public function test_empty_state_and_responsive_keyboard_markup_meet_the_accessibility_contract(): void
    {
        $empty = $this->get(route('home'))->assertOk()
            ->assertSee('data-menu-render="desktop" data-menu-fallback="false"', false)
            ->assertSee('data-menu-render="mobile" data-menu-fallback="false"', false)
            ->assertSee('data-menu-render="footer" data-menu-fallback="false"', false);
        $this->assertStringNotContainsString('data-menu-fallback="true"', $empty->getContent());

        $actor = $this->actor('admin');
        $group = app(MenuService::class)->createMenu($actor, [
            'name_vi' => 'Nhóm bàn phím', 'name_en' => 'Keyboard group',
            'link_type' => 'none', 'target' => '_self', 'location' => 'header',
            'sort_order' => 10, 'status' => true,
        ]);
        app(MenuService::class)->createMenu($actor, [
            'parent_id' => $group->id,
            'name_vi' => 'Mở cửa hàng', 'name_en' => 'Open shop',
            'link_type' => 'route', 'route_name' => 'shop', 'target' => '_blank',
            'location' => 'header', 'sort_order' => 10, 'status' => true,
        ]);

        $this->get(route('shop'))->assertOk()
            ->assertSee('data-menu-toggle aria-expanded="false"', false)
            ->assertSee('drawer-menu__group--level-1', false)
            ->assertSee('<summary>Nhóm bàn phím</summary>', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);

        $javascript = file_get_contents(public_path('assets/frontend/js/main.js'));
        $css = file_get_contents(public_path('assets/frontend/css/styles.css'));
        $this->assertStringContainsString("e.key === 'Escape'", $javascript);
        $this->assertStringContainsString("querySelectorAll('[data-menu-toggle]')", $javascript);
        $this->assertStringContainsString("setAttribute('aria-expanded'", $javascript);
        $this->assertStringContainsString("directToggle(item)?.focus()", $javascript);
        $this->assertStringContainsString("getBoundingClientRect().right", $javascript);
        $this->assertStringContainsString('.main-nav__item.opens-left', $css);
        $this->assertStringContainsString('.main-nav__item--level-1.opens-left', $css);
        $this->assertStringContainsString('.footer-menu__list .footer-menu__list', $css);
        $this->assertStringContainsString('.main-nav__item:focus-within', $css);
    }

    private function actor(string $roleName): User
    {
        $actor = User::factory()->create(['status' => 'active']);
        $actor->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $actor;
    }

    /** @return array<string, mixed> */
    private function payload(string $name): array
    {
        return [
            'parent_id' => null,
            'name_vi' => $name,
            'name_en' => $name,
            'link_type' => 'route',
            'route_name' => 'contact',
            'url' => null,
            'target' => '_self',
            'location' => 'header',
            'sort_order' => 10,
            'status' => true,
        ];
    }
}
