<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Services\MenuService;
use App\Support\StorefrontLocaleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->app->setLocale('vi');
    }

    public function test_header_tree_is_shared_by_desktop_and_mobile_and_footer_uses_its_own_location(): void
    {
        $group = $this->menu(['name_vi' => 'Mua sắm', 'name_en' => 'Shopping', 'link_type' => 'none']);
        $this->menu([
            'parent_id' => $group->id,
            'name_vi' => 'Cửa hàng',
            'name_en' => 'Shop',
            'link_type' => 'route',
            'route_name' => 'shop',
            'target' => '_blank',
        ]);
        $footer = $this->menu([
            'name_vi' => 'Hỗ trợ',
            'name_en' => 'Support',
            'link_type' => 'none',
            'location' => 'footer',
        ]);
        $this->menu([
            'parent_id' => $footer->id,
            'name_vi' => 'Liên hệ',
            'name_en' => 'Contact',
            'link_type' => 'route',
            'route_name' => 'contact',
            'location' => 'footer',
        ]);

        $response = $this->get(route('shop'));

        $response->assertOk()
            ->assertSee('data-menu-render="desktop"', false)
            ->assertSee('data-menu-render="mobile"', false)
            ->assertSee('data-menu-render="footer"', false)
            ->assertSeeInOrder(['Mua sắm', 'Cửa hàng'], false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('drawer-menu__group--level-1', false)
            ->assertSee('Hỗ trợ')
            ->assertSee('Liên hệ');

        $this->assertGreaterThanOrEqual(2, substr_count($response->getContent(), 'Mua sắm'));
        $this->assertSame(2, substr_count($response->getContent(), 'Cửa hàng'));
    }

    public function test_locale_selection_and_label_fallback_are_applied_to_both_renderers(): void
    {
        $this->menu(['name_vi' => 'Trang chủ', 'name_en' => 'Homepage', 'link_type' => 'route', 'route_name' => 'home']);
        $this->menu(['name_vi' => 'Chỉ tiếng Việt', 'name_en' => '', 'link_type' => 'route', 'route_name' => 'contact']);

        $response = $this
            ->withSession([StorefrontLocaleResolver::SESSION_KEY => 'en'])
            ->get(route('home'));

        $response->assertOk()->assertSee('Homepage')->assertSee('Chỉ tiếng Việt');
        $this->assertSame(2, substr_count($response->getContent(), 'Homepage'));
        $this->assertSame(2, substr_count($response->getContent(), 'Chỉ tiếng Việt'));
    }

    public function test_disabled_parent_hides_its_children_and_unsafe_legacy_item_is_not_rendered(): void
    {
        $hidden = $this->menu(['name_vi' => 'Nhóm ẩn', 'name_en' => 'Hidden', 'link_type' => 'none', 'status' => false]);
        $this->menu([
            'parent_id' => $hidden->id,
            'name_vi' => 'Con bị ẩn',
            'name_en' => 'Hidden child',
            'link_type' => 'route',
            'route_name' => 'shop',
        ]);
        $this->menu([
            'name_vi' => '<script>alert(1)</script>',
            'name_en' => 'Unsafe',
            'link_type' => 'url',
            'url' => 'javascript:alert(1)',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Nhóm ẩn')
            ->assertDontSee('Con bị ẩn')
            ->assertDontSee('javascript:alert(1)', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_transitional_fallback_keeps_header_and_footer_available_when_database_is_empty(): void
    {
        config()->set('menus.fallback_enabled', true);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-menu-render="desktop" data-menu-fallback="true"', false)
            ->assertSee('data-menu-render="mobile" data-menu-fallback="true"', false)
            ->assertSee('data-menu-render="footer" data-menu-fallback="true"', false)
            ->assertSee('Trang chủ')
            ->assertSee('Danh mục phổ biến');
    }

    public function test_published_trees_are_queried_once_then_reused_from_location_locale_cache(): void
    {
        $this->menu(['name_vi' => 'Trang chủ', 'name_en' => 'Home', 'link_type' => 'route', 'route_name' => 'home']);
        $this->menu(['name_vi' => 'Liên hệ', 'name_en' => 'Contact', 'link_type' => 'route', 'route_name' => 'contact', 'location' => 'footer']);
        Cache::flush();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('home'))->assertOk();
        $firstMenuQueries = $this->menuQueryCount(DB::getQueryLog());

        DB::flushQueryLog();
        $this->get(route('contact'))->assertOk();
        $secondMenuQueries = $this->menuQueryCount(DB::getQueryLog());

        $this->assertSame(4, $firstMenuQueries);
        $this->assertSame(0, $secondMenuQueries);
    }

    public function test_desktop_mobile_and_footer_render_the_same_four_level_tree_recursively(): void
    {
        $root = $this->menu([
            'name_vi' => 'Root bốn cấp', 'name_en' => 'Four-level root',
            'link_type' => 'route', 'route_name' => 'shop',
        ]);
        $levelTwo = $this->menu([
            'parent_id' => $root->id, 'name_vi' => 'Nhóm cấp hai', 'name_en' => 'Level two',
        ]);
        $levelThree = $this->menu([
            'parent_id' => $levelTwo->id, 'name_vi' => 'Nhóm cấp ba', 'name_en' => 'Level three',
        ]);
        $this->menu([
            'parent_id' => $levelThree->id, 'name_vi' => 'Đích cấp bốn', 'name_en' => 'Level four target',
            'link_type' => 'route', 'route_name' => 'contact', 'target' => '_blank',
        ]);

        $footerRoot = $this->menu([
            'name_vi' => 'Footer root', 'name_en' => 'Footer root', 'location' => 'footer',
        ]);
        $footerTwo = $this->menu([
            'parent_id' => $footerRoot->id, 'name_vi' => 'Footer hai', 'name_en' => 'Footer two', 'location' => 'footer',
        ]);
        $footerThree = $this->menu([
            'parent_id' => $footerTwo->id, 'name_vi' => 'Footer ba', 'name_en' => 'Footer three', 'location' => 'footer',
        ]);
        $this->menu([
            'parent_id' => $footerThree->id, 'name_vi' => 'Footer bốn', 'name_en' => 'Footer four',
            'link_type' => 'route', 'route_name' => 'help', 'location' => 'footer',
        ]);

        $response = $this->get(route('contact'))->assertOk();
        $content = $response->getContent();

        $response->assertSee('main-nav__item--level-4', false)
            ->assertSee('data-menu-submenu data-menu-depth="4"', false)
            ->assertSee('drawer-menu__group--level-3', false)
            ->assertSee('drawer-menu__link--level-4', false)
            ->assertSee('footer-menu__item--level-4', false)
            ->assertSee('footer-menu__list--level-4', false)
            ->assertSee('aria-controls="desktop-submenu-'.$root->id.'"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('aria-current="page"', false);

        $this->assertGreaterThanOrEqual(3, substr_count($content, 'Root bốn cấp'));
        $this->assertSame(2, substr_count($content, 'Đích cấp bốn'));
        $this->assertGreaterThanOrEqual(3, substr_count($content, '<details class="drawer-menu__group'));
        $this->assertMatchesRegularExpression('/drawer-menu__group--level-1"[^>]*\sopen\s*>/', $content);
        $this->assertMatchesRegularExpression('/drawer-menu__group--level-3"[^>]*\sopen\s*>/', $content);
    }

    /** @param array<string, mixed> $attributes */
    private function menu(array $attributes): Menu
    {
        return Menu::query()->create(array_merge([
            'parent_id' => null,
            'name_vi' => 'Menu',
            'name_en' => 'Menu',
            'link_type' => 'none',
            'route_name' => null,
            'url' => null,
            'target' => '_self',
            'location' => 'header',
            'sort_order' => Menu::query()->count() * 10 + 10,
            'status' => true,
        ], $attributes));
    }

    /** @param array<int, array{query: string, bindings: array<int, mixed>, time: float|null}> $queries */
    private function menuQueryCount(array $queries): int
    {
        return collect($queries)
            ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'menus'))
            ->count();
    }
}
