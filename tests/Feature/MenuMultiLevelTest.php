<?php

namespace Tests\Feature;

use App\Exceptions\MenuOperationException;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Services\MenuService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MenuMultiLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_creates_four_levels_and_rejects_level_five(): void
    {
        $service = app(MenuService::class);
        $actor = $this->actor();
        [$root, $levelTwo, $levelThree, $levelFour] = $this->fourLevelTree($service, $actor);

        $this->assertSame(1, $service->depth($root));
        $this->assertSame(4, $service->depth($levelFour));
        $this->assertSame([$levelThree->id, $levelTwo->id, $root->id], $service->ancestorIds($levelFour));
        $this->assertSame(4, $service->subtreeHeight($root));
        $this->assertSame(1, $service->subtreeHeight($levelFour));

        $this->expectMenuFailure(fn () => $service->createMenu($actor, [
            ...$this->payload('Too deep', 'home'),
            'parent_id' => $levelFour->id,
        ]));
        $this->assertSame(4, Menu::count());
    }

    public function test_parent_guard_blocks_cycle_cross_location_and_branch_overflow(): void
    {
        $service = app(MenuService::class);
        $actor = $this->actor();
        [$root, $levelTwo, $levelThree] = $this->fourLevelTree($service, $actor);
        $otherRoot = $service->createMenu($actor, $this->payload('Other root', 'home'));
        $otherLevelTwo = $service->createMenu($actor, [
            ...$this->payload('Other child', 'shop'),
            'parent_id' => $otherRoot->id,
        ]);
        $footerRoot = $service->createMenu($actor, $this->payload('Footer', 'contact', 'footer'));

        $this->expectMenuFailure(fn () => $service->updateMenu($actor, $root, ['parent_id' => $levelThree->id]));
        $this->expectMenuFailure(fn () => $service->updateMenu($actor, $levelTwo, ['parent_id' => $otherLevelTwo->id]));
        $this->expectMenuFailure(fn () => $service->updateMenu($actor, $levelThree, ['parent_id' => $footerRoot->id]));

        $this->assertNull($root->fresh()->parent_id);
        $this->assertSame($root->id, $levelTwo->fresh()->parent_id);
    }

    public function test_published_tree_returns_four_levels_with_depth_and_hides_inactive_branch(): void
    {
        $service = app(MenuService::class);
        $actor = $this->actor();
        [$root, $levelTwo, $levelThree, $levelFour] = $this->fourLevelTree($service, $actor);

        $tree = $service->published('header', 'en', 'contact', '/contact');

        $this->assertSame($root->id, $tree[0]['id']);
        $this->assertSame(1, $tree[0]['depth']);
        $this->assertSame(2, $tree[0]['children'][0]['depth']);
        $this->assertSame(3, $tree[0]['children'][0]['children'][0]['depth']);
        $this->assertSame(4, $tree[0]['children'][0]['children'][0]['children'][0]['depth']);
        $this->assertSame($levelFour->id, $tree[0]['children'][0]['children'][0]['children'][0]['id']);
        $this->assertTrue($tree[0]['active']);

        $service->updateStatus($actor, $levelTwo, false);
        $tree = $service->published('header', 'en', 'contact', '/contact');

        $this->assertSame([], $tree);
        $this->assertTrue($levelThree->fresh()->status);
    }

    public function test_published_query_count_is_bounded_by_depth_and_warm_cache_reads_no_menu_rows(): void
    {
        $service = app(MenuService::class);
        $actor = $this->actor();
        $this->fourLevelTree($service, $actor);
        Cache::flush();
        $menuQueries = 0;

        DB::listen(function ($query) use (&$menuQueries): void {
            if (preg_match('/\bfrom\s+["`]?menus["`]?\b/i', $query->sql)) {
                $menuQueries++;
            }
        });

        $service->published('header', 'vi', 'contact', '/contact');
        $coldQueries = $menuQueries;
        $service->published('header', 'vi', 'contact', '/contact');

        $this->assertGreaterThanOrEqual(1, $coldQueries);
        $this->assertLessThanOrEqual(4, $coldQueries);
        $this->assertSame($coldQueries, $menuQueries);
    }

    public function test_helpers_reject_legacy_cycle_instead_of_looping(): void
    {
        $service = app(MenuService::class);
        $root = Menu::create($this->payload('Root', 'home'));
        $child = Menu::create([...$this->payload('Child', 'shop'), 'parent_id' => $root->id]);
        $root->update(['parent_id' => $child->id]);

        $this->expectMenuFailure(fn () => $service->depth($root->fresh()));
        $this->expectMenuFailure(fn () => $service->subtreeHeight($root->fresh()));
    }

    /** @return array<int, Menu> */
    private function fourLevelTree(MenuService $service, User $actor): array
    {
        $root = $service->createMenu($actor, $this->payload('Root', null));
        $levelTwo = $service->createMenu($actor, [...$this->payload('Level 2', null), 'parent_id' => $root->id]);
        $levelThree = $service->createMenu($actor, [...$this->payload('Level 3', null), 'parent_id' => $levelTwo->id]);
        $levelFour = $service->createMenu($actor, [...$this->payload('Level 4', 'contact'), 'parent_id' => $levelThree->id]);

        return [$root, $levelTwo, $levelThree, $levelFour];
    }

    /** @return array<string, mixed> */
    private function payload(string $name, ?string $routeName, string $location = 'header'): array
    {
        return [
            'name_vi' => $name,
            'name_en' => $name,
            'link_type' => $routeName === null ? 'none' : 'route',
            'route_name' => $routeName,
            'target' => '_self',
            'location' => $location,
            'sort_order' => 10,
            'status' => true,
        ];
    }

    private function actor(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $actor = User::factory()->create();
        $actor->roles()->attach(Role::where('name', 'admin')->firstOrFail());

        return $actor;
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
