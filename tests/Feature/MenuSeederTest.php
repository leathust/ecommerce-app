<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Services\MenuService;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MenuSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->app->setLocale('vi');
    }

    public function test_seeder_backfills_both_locations_and_is_idempotent(): void
    {
        Cache::put('menus.published.v2.header.vi', ['stale']);

        $this->seed(MenuSeeder::class);
        $firstSnapshot = Menu::query()->orderBy('id')->get()->map->only([
            'id', 'parent_id', 'name_vi', 'name_en', 'link_type', 'route_name',
            'url', 'target', 'location', 'sort_order', 'status',
        ])->all();

        $this->seed(MenuSeeder::class);
        $secondSnapshot = Menu::query()->orderBy('id')->get()->map->only([
            'id', 'parent_id', 'name_vi', 'name_en', 'link_type', 'route_name',
            'url', 'target', 'location', 'sort_order', 'status',
        ])->all();

        $this->assertSame($this->defaultItemCount(), Menu::count());
        $this->assertSame($firstSnapshot, $secondSnapshot);
        $this->assertSame(5, Menu::query()->forLocation('header')->count());
        $this->assertSame(16, Menu::query()->forLocation('footer')->count());
        $this->assertFalse(Cache::has('menus.published.v2.header.vi'));
        $this->assertDatabaseHas('menus', ['location' => 'header', 'route_name' => 'home', 'status' => true]);
        $this->assertDatabaseHas('menus', ['location' => 'footer', 'name_en' => 'Popular Categories', 'link_type' => 'none']);
    }

    public function test_existing_custom_location_is_never_merged_or_overwritten(): void
    {
        $custom = Menu::query()->create([
            'name_vi' => 'Điều hướng riêng',
            'name_en' => 'Custom navigation',
            'link_type' => 'url',
            'url' => '/custom',
            'target' => '_blank',
            'location' => 'header',
            'sort_order' => 77,
            'status' => false,
        ]);

        $this->seed(MenuSeeder::class);

        $this->assertSame(1, Menu::query()->forLocation('header')->count());
        $this->assertSame([
            'name_vi' => 'Điều hướng riêng',
            'name_en' => 'Custom navigation',
            'url' => '/custom',
            'target' => '_blank',
            'sort_order' => 77,
            'status' => false,
        ], $custom->fresh()->only(['name_vi', 'name_en', 'url', 'target', 'sort_order', 'status']));
        $this->assertSame(16, Menu::query()->forLocation('footer')->count());
    }

    public function test_default_destinations_are_allowlisted_existing_storefront_routes(): void
    {
        $definitions = collect(config('menus.defaults'))
            ->flatMap(fn (array $roots): array => $this->flattenDefinitions($roots));
        $routes = $definitions->pluck('route_name')->filter()->unique()->values();

        $this->assertNotEmpty($routes);

        foreach ($routes as $routeName) {
            $this->assertArrayHasKey($routeName, config('menus.routes'));
            $this->assertTrue(Route::has($routeName), "Default menu route [{$routeName}] must exist.");
            $this->assertFalse(str_starts_with($routeName, 'admin.'));
        }
    }

    public function test_storefront_uses_seeded_database_tree_with_emergency_fallback_disabled(): void
    {
        config()->set('menus.fallback_enabled', false);
        $this->seed(MenuSeeder::class);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-menu-fallback="false"', false)
            ->assertSee('Trang chủ')
            ->assertSee('Danh mục phổ biến');
    }

    public function test_seeder_supports_four_level_defaults_and_remains_idempotent(): void
    {
        config()->set('menus.defaults', [
            'header' => [[
                'label_vi' => 'Cấp một', 'label_en' => 'Level one',
                'children' => [[
                    'label_vi' => 'Cấp hai', 'label_en' => 'Level two',
                    'children' => [[
                        'label_vi' => 'Cấp ba', 'label_en' => 'Level three',
                        'children' => [[
                            'label_vi' => 'Cấp bốn', 'label_en' => 'Level four',
                            'route_name' => 'shop',
                        ]],
                    ]],
                ]],
            ]],
        ]);
        Cache::put('menus.published.v2.header.vi', ['stale']);

        $this->seed(MenuSeeder::class);
        $firstSnapshot = Menu::query()->orderBy('id')->get()->map->only([
            'id', 'parent_id', 'name_vi', 'name_en', 'link_type', 'route_name',
            'location', 'sort_order', 'status',
        ])->all();

        $this->seed(MenuSeeder::class);

        $this->assertSame(4, Menu::count());
        $this->assertSame($firstSnapshot, Menu::query()->orderBy('id')->get()->map->only([
            'id', 'parent_id', 'name_vi', 'name_en', 'link_type', 'route_name',
            'location', 'sort_order', 'status',
        ])->all());
        $this->assertSame(4, app(MenuService::class)->depth(Menu::query()->where('name_vi', 'Cấp bốn')->firstOrFail()));
        $this->assertFalse(Cache::has('menus.published.v2.header.vi'));
    }

    public function test_existing_two_and_four_level_trees_survive_seeding_without_data_migration(): void
    {
        $root = $this->menu('Header cũ', null, 'header');
        $this->menu('Con cấp hai', $root->id, 'header');

        $footerRoot = $this->menu('Footer cấp một', null, 'footer');
        $footerTwo = $this->menu('Footer cấp hai', $footerRoot->id, 'footer');
        $footerThree = $this->menu('Footer cấp ba', $footerTwo->id, 'footer');
        $this->menu('Footer cấp bốn', $footerThree->id, 'footer');
        $before = Menu::query()->orderBy('id')->get()->map->getAttributes()->all();

        $this->seed(MenuSeeder::class);

        $this->assertSame($before, Menu::query()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(2, Menu::query()->forLocation('header')->count());
        $this->assertSame(4, Menu::query()->forLocation('footer')->count());
        $this->assertSame(4, app(MenuService::class)->depth(Menu::query()->where('name_vi', 'Footer cấp bốn')->firstOrFail()));
    }

    public function test_seeder_rejects_defaults_beyond_maximum_depth_without_partial_rows(): void
    {
        config()->set('menus.max_depth', 2);
        config()->set('menus.defaults', [
            'header' => [[
                'label_vi' => 'Một', 'label_en' => 'One',
                'children' => [[
                    'label_vi' => 'Hai', 'label_en' => 'Two',
                    'children' => [[
                        'label_vi' => 'Ba', 'label_en' => 'Three',
                    ]],
                ]],
            ]],
        ]);

        try {
            $this->seed(MenuSeeder::class);
            $this->fail('Seeder should reject defaults deeper than menus.max_depth.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('exceeds the configured maximum depth', $exception->getMessage());
        }

        $this->assertDatabaseCount('menus', 0);
    }

    public function test_menu_check_command_is_read_only_and_accepts_a_valid_four_level_tree(): void
    {
        $root = $this->menu('Một', null, 'header');
        $two = $this->menu('Hai', $root->id, 'header');
        $three = $this->menu('Ba', $two->id, 'header');
        $this->menu('Bốn', $three->id, 'header');
        $before = Menu::query()->orderBy('id')->get()->map->getAttributes()->all();

        $this->artisan('menus:check')
            ->expectsOutputToContain('depth lớn nhất: 4')
            ->expectsOutputToContain('Cây Menu hợp lệ')
            ->assertSuccessful();

        $this->assertSame($before, Menu::query()->orderBy('id')->get()->map->getAttributes()->all());
    }

    public function test_menu_check_command_reports_cycle_and_depth_overflow_without_mutation(): void
    {
        config()->set('menus.max_depth', 2);
        $root = $this->menu('Một', null, 'header');
        $two = $this->menu('Hai', $root->id, 'header');
        $three = $this->menu('Ba', $two->id, 'header');
        $root->update(['parent_id' => $three->id]);
        $before = Menu::query()->orderBy('id')->get()->map->getAttributes()->all();

        $this->artisan('menus:check')
            ->expectsOutputToContain('Vòng lặp:')
            ->expectsOutputToContain('Vượt depth:')
            ->expectsOutputToContain('Lệnh chỉ đọc')
            ->assertExitCode(1);

        $this->assertSame($before, Menu::query()->orderBy('id')->get()->map->getAttributes()->all());
    }

    private function defaultItemCount(): int
    {
        return collect(config('menus.defaults'))->sum(fn (array $roots): int => $this->countDefinitions($roots));
    }

    /** @param array<int, array<string, mixed>> $definitions */
    private function countDefinitions(array $definitions): int
    {
        return collect($definitions)->sum(
            fn (array $definition): int => 1 + $this->countDefinitions($definition['children'] ?? []),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $definitions
     * @return array<int, array<string, mixed>>
     */
    private function flattenDefinitions(array $definitions): array
    {
        $flattened = [];

        foreach ($definitions as $definition) {
            $flattened[] = $definition;
            array_push($flattened, ...$this->flattenDefinitions($definition['children'] ?? []));
        }

        return $flattened;
    }

    private function menu(string $name, ?int $parentId, string $location): Menu
    {
        return Menu::query()->create([
            'parent_id' => $parentId,
            'name_vi' => $name,
            'name_en' => $name,
            'link_type' => 'none',
            'target' => '_self',
            'location' => $location,
            'sort_order' => Menu::query()->forLocation($location)->count() * 10 + 10,
            'status' => true,
        ]);
    }
}
