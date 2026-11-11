<?php

namespace Tests\Feature;

use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MenuSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_menu_config_and_schema_contract_are_available(): void
    {
        $this->assertSame(['header', 'footer'], array_keys(config('menus.locations')));
        $this->assertSame(['route', 'url', 'none'], array_keys(config('menus.link_types')));
        $this->assertSame(['_self', '_blank'], array_keys(config('menus.targets')));
        $this->assertSame(4, config('menus.max_depth'));
        $this->assertSame('menus.published.v2', config('menus.cache.prefix'));

        foreach (array_keys(config('menus.routes')) as $routeName) {
            $this->assertTrue(Route::has($routeName), "Configured menu route [{$routeName}] does not exist.");
            $this->assertFalse(str_starts_with($routeName, 'admin.'));
        }

        $this->assertTrue(Schema::hasColumns('menus', [
            'parent_id',
            'name_vi',
            'name_en',
            'link_type',
            'route_name',
            'url',
            'target',
            'location',
            'sort_order',
            'status',
            'created_at',
            'updated_at',
        ]));

        $indexes = collect(DB::select("PRAGMA index_list('menus')"))->pluck('name');
        $this->assertTrue($indexes->contains('menus_published_tree_index'));
    }

    public function test_menu_model_scopes_relations_order_and_localized_name(): void
    {
        $root = Menu::create([
            'name_vi' => 'Sản phẩm',
            'name_en' => 'Products',
            'link_type' => 'route',
            'route_name' => 'products',
            'location' => 'header',
            'sort_order' => 20,
            'status' => true,
        ]);
        $firstChild = Menu::create([
            'parent_id' => $root->id,
            'name_vi' => 'Điện thoại',
            'name_en' => 'Phones',
            'url' => '/shop?category=phones',
            'location' => 'header',
            'sort_order' => 20,
            'status' => true,
        ]);
        $secondChild = Menu::create([
            'parent_id' => $root->id,
            'name_vi' => 'Máy tính',
            'name_en' => 'Computers',
            'url' => '/shop?category=computers',
            'location' => 'header',
            'sort_order' => 10,
            'status' => true,
        ]);
        Menu::create([
            'name_vi' => 'Ẩn',
            'name_en' => 'Hidden',
            'link_type' => 'none',
            'location' => 'footer',
            'status' => false,
        ]);

        $this->assertTrue($root->parent()->doesntExist());
        $this->assertSame([$secondChild->id, $firstChild->id], $root->children->pluck('id')->all());
        $this->assertSame(
            [$root->id],
            Menu::query()->forLocation('header')->roots()->active()->ordered()->pluck('id')->all(),
        );
        $this->assertSame('Sản phẩm', $root->localizedName('vi'));
        $this->assertSame('Products', $root->localizedName('en'));
        $this->assertSame('Sản phẩm', $root->localizedName('fr'));
        $this->assertFalse($root->isContainer());
        $this->assertTrue(Menu::where('location', 'footer')->firstOrFail()->isContainer());
        $this->assertIsInt($root->sort_order);
        $this->assertTrue($root->status);
        $this->assertNotNull($root->created_at);
    }

    public function test_additive_migration_preserves_and_backfills_legacy_menu_rows(): void
    {
        $routeMenuId = DB::table('menus')->insertGetId([
            'name_vi' => 'Trang chủ',
            'name_en' => 'Home',
            'url' => '/',
            'location' => 'header',
            'sort_order' => 0,
            'status' => true,
        ]);
        $containerId = DB::table('menus')->insertGetId([
            'name_vi' => 'Hỗ trợ',
            'name_en' => 'Support',
            'url' => null,
            'location' => 'footer',
            'sort_order' => 0,
            'status' => true,
        ]);
        $migration = require database_path('migrations/2026_11_09_000010_extend_menus_table_for_dynamic_navigation.php');

        $migration->down();

        $this->assertFalse(Schema::hasColumn('menus', 'link_type'));
        $this->assertDatabaseHas('menus', ['id' => $routeMenuId, 'url' => '/']);
        $this->assertDatabaseHas('menus', ['id' => $containerId, 'url' => null]);

        $migration->up();

        $this->assertDatabaseHas('menus', [
            'id' => $routeMenuId,
            'url' => '/',
            'link_type' => 'url',
            'target' => '_self',
        ]);
        $this->assertDatabaseHas('menus', [
            'id' => $containerId,
            'url' => null,
            'link_type' => 'none',
            'target' => '_self',
        ]);
        $this->assertTrue(Schema::hasColumns('menus', ['route_name', 'created_at', 'updated_at']));
    }
}
