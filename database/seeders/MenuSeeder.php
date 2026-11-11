<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Services\MenuService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    /**
     * Backfill only completely empty locations. An existing location belongs
     * to administrators and is never merged with or overwritten by defaults.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (config('menus.defaults', []) as $location => $roots) {
                $hasExistingItems = Menu::query()
                    ->forLocation((string) $location)
                    ->lockForUpdate()
                    ->exists();

                if ($hasExistingItems) {
                    continue;
                }

                $this->seedLevel((string) $location, $roots, null, 1);
            }
        });

        app(MenuService::class)->forgetPublishedCache();
    }

    /**
     * Seed a configured tree recursively while enforcing the same maximum
     * depth as runtime mutations. Defaults beyond that contract must fail
     * loudly instead of being partially inserted.
     *
     * @param array<int, array<string, mixed>> $definitions
     */
    private function seedLevel(string $location, array $definitions, ?int $parentId, int $depth): void
    {
        if ($depth > (int) config('menus.max_depth', 4)) {
            throw new \InvalidArgumentException("Default menu [{$location}] exceeds the configured maximum depth.");
        }

        foreach ($definitions as $index => $definition) {
            $item = $this->firstOrCreateItem(
                $location,
                $parentId,
                $definition,
                ($index + 1) * (int) config('menus.sort_step', 10),
            );

            $children = $definition['children'] ?? [];

            if ($children !== []) {
                $this->seedLevel($location, $children, $item->id, $depth + 1);
            }
        }
    }

    /**
     * Natural identity is scoped by location + parent + link destination +
     * bilingual default label. Different labels may intentionally share a route.
     *
     * @param array<string, mixed> $definition
     */
    private function firstOrCreateItem(string $location, ?int $parentId, array $definition, int $sortOrder): Menu
    {
        $routeName = $definition['route_name'] ?? null;
        $url = $definition['url'] ?? null;
        $linkType = $routeName !== null ? 'route' : ($url !== null ? 'url' : 'none');
        $identity = [
            'location' => $location,
            'parent_id' => $parentId,
            'link_type' => $linkType,
            'name_vi' => (string) $definition['label_vi'],
            'name_en' => (string) $definition['label_en'],
        ];

        if ($linkType === 'route') {
            $identity['route_name'] = $routeName;
        } elseif ($linkType === 'url') {
            $identity['url'] = $url;
        }

        return Menu::query()->firstOrCreate($identity, [
            'name_vi' => (string) $definition['label_vi'],
            'name_en' => (string) $definition['label_en'],
            'route_name' => $routeName,
            'url' => $url,
            'target' => (string) ($definition['target'] ?? '_self'),
            'sort_order' => $sortOrder,
            'status' => true,
        ]);
    }
}
