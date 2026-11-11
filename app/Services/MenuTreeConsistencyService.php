<?php

namespace App\Services;

use App\Models\Menu;

class MenuTreeConsistencyService
{
    /**
     * Inspect the adjacency list without mutating it.
     *
     * @return array{
     *     total: int,
     *     roots: int,
     *     deepest: int,
     *     cycles: array<int, string>,
     *     missing_parents: array<int, string>,
     *     cross_location: array<int, string>,
     *     depth_overflow: array<int, string>
     * }
     */
    public function inspect(): array
    {
        $menus = Menu::query()
            ->select(['id', 'parent_id', 'location', 'name_vi'])
            ->get()
            ->keyBy('id');
        $maxDepth = (int) config('menus.max_depth', 4);
        $issues = [
            'cycles' => [],
            'missing_parents' => [],
            'cross_location' => [],
            'depth_overflow' => [],
        ];
        $deepest = 0;

        foreach ($menus as $menu) {
            $current = $menu;
            $visited = [];
            $depth = 0;

            while ($current !== null) {
                if (isset($visited[$current->id])) {
                    $issues['cycles'][] = "Menu #{$menu->id} đi qua vòng lặp tại #{$current->id}.";
                    break;
                }

                $visited[$current->id] = true;
                $depth++;

                if ($current->parent_id === null) {
                    break;
                }

                $parent = $menus->get($current->parent_id);

                if ($parent === null) {
                    $issues['missing_parents'][] = "Menu #{$current->id} tham chiếu parent #{$current->parent_id} không tồn tại.";
                    break;
                }

                if ($parent->location !== $current->location) {
                    $issues['cross_location'][] = "Menu #{$current->id} ({$current->location}) có parent #{$parent->id} ({$parent->location}).";
                }

                $current = $parent;
            }

            $deepest = max($deepest, $depth);

            if ($depth > $maxDepth) {
                $issues['depth_overflow'][] = "Menu #{$menu->id} có depth {$depth}, vượt giới hạn {$maxDepth}.";
            }
        }

        foreach (array_keys($issues) as $key) {
            $issues[$key] = array_values(array_unique($issues[$key]));
        }

        return [
            'total' => $menus->count(),
            'roots' => $menus->whereNull('parent_id')->count(),
            'deepest' => $deepest,
            ...$issues,
        ];
    }
}
