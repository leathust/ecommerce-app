<?php

namespace App\Http\Controllers;

use App\Exceptions\MenuOperationException;
use App\Http\Requests\AdminMenuIndexRequest;
use App\Http\Requests\MoveMenuRequest;
use App\Http\Requests\StoreMenuRequest;
use App\Http\Requests\UpdateMenuRequest;
use App\Http\Requests\ReorderMenusRequest;
use App\Http\Requests\UpdateMenuStatusRequest;
use App\Models\Menu;
use App\Services\MenuService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AdminMenuController extends Controller
{
    public function __construct(private readonly MenuService $menus) {}

    public function index(AdminMenuIndexRequest $request): View
    {
        $filters = $request->validated();
        $filters['location'] = $filters['location'] ?? array_key_first(config('menus.locations', []));
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? null;
        $matchSearch = static function (Builder $query) use ($search): void {
            $query->where(function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where('name_vi', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('route_name', 'like', $like)
                    ->orWhere('url', 'like', $like);
            });
        };

        $query = Menu::query()
            ->forLocation($filters['location'])
            ->roots()
            ->withCount('children')
            ->with($this->treeRelations())
            ->when(in_array($status, ['active', 'inactive'], true), function (Builder $query) use ($status): void {
                $active = $status === 'active';
                $query->where(function (Builder $query) use ($active): void {
                    $query->where('status', $active);

                    foreach ($this->descendantPaths() as $path) {
                        $query->orWhereHas($path, fn (Builder $query) => $query->where('status', $active));
                    }
                });
            })
            ->when($search !== '', function (Builder $query) use ($matchSearch): void {
                $query->where(function (Builder $query) use ($matchSearch): void {
                    $matchSearch($query);

                    foreach ($this->descendantPaths() as $path) {
                        $query->orWhereHas($path, $matchSearch);
                    }
                });
            })
            ->ordered();

        $menuTrees = $query->paginate(10)->withQueryString();
        $scopeIdsByParent = Menu::query()
            ->forLocation($filters['location'])
            ->ordered()
            ->get(['id', 'parent_id'])
            ->groupBy(fn (Menu $menu): string => $menu->parent_id === null ? 'root' : (string) $menu->parent_id)
            ->map(fn (Collection $items): array => $items->pluck('id')->all())
            ->all();
        $allMenus = Menu::query()->ordered()->get()->keyBy('id');
        $moveCatalog = $this->moveCatalog($allMenus);

        return view('admin.menus.index', [
            'menuTrees' => $menuTrees,
            'filters' => $filters,
            'locations' => config('menus.locations', []),
            'totalMenus' => Menu::query()->count(),
            'scopeIdsByParent' => $scopeIdsByParent,
            'moveCatalog' => $moveCatalog,
            'moveCatalogById' => collect($moveCatalog)->keyBy('id'),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Menu([
            'location' => array_key_first(config('menus.locations', [])),
            'link_type' => 'route',
            'target' => '_self',
            'sort_order' => 0,
            'status' => true,
        ]));
    }

    public function store(StoreMenuRequest $request): RedirectResponse
    {
        try {
            $menu = $this->menus->createMenu($request->user(), $request->validated(), $request);
        } catch (MenuOperationException $exception) {
            return back()->withInput()->withErrors(['menu' => $exception->getMessage()]);
        }

        return redirect()->route('admin.menus.index', ['location' => $menu->location])
            ->with('success', 'Đã thêm menu website.');
    }

    public function edit(Menu $menu): View
    {
        return $this->form($menu);
    }

    public function moveForm(Menu $menu): View
    {
        $allMenus = Menu::query()->ordered()->get()->keyBy('id');
        $catalog = collect($this->moveCatalog($allMenus));
        $subject = $catalog->firstWhere('id', $menu->id);

        abort_if($subject === null, 422, 'Cây menu hiện tại không hợp lệ để di chuyển.');

        return view('admin.menus.move', [
            'menu' => $menu,
            'subject' => $subject,
            'parents' => $this->validMoveParents($catalog, $subject),
            'locations' => config('menus.locations', []),
        ]);
    }

    public function move(MoveMenuRequest $request, Menu $menu): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        try {
            $menu = $this->menus->moveMenu(
                $request->user(),
                $menu,
                $validated['parent_id'] ?? null,
                $validated['location'],
                $validated['source_parent_id'] ?? null,
                $validated['source_location'],
                $request,
            );
        } catch (MenuOperationException $exception) {
            return $this->mutationError($request, $exception);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Đã di chuyển nhánh menu.',
                'menu' => [
                    'id' => $menu->id,
                    'parent_id' => $menu->parent_id,
                    'location' => $menu->location,
                    'sort_order' => $menu->sort_order,
                ],
            ]);
        }

        return redirect()->route('admin.menus.index', ['location' => $menu->location])
            ->with('success', 'Đã di chuyển nhánh menu.');
    }

    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        try {
            $menu = $this->menus->updateMenu($request->user(), $menu, $request->validated(), $request);
        } catch (MenuOperationException $exception) {
            return back()->withInput()->withErrors(['menu' => $exception->getMessage()]);
        }

        return redirect()->route('admin.menus.index', ['location' => $menu->location])
            ->with('success', 'Đã cập nhật menu website.');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $location = $menu->location;

        try {
            $this->menus->deleteMenu(request()->user(), $menu, request());
        } catch (MenuOperationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.menus.index', ['location' => $location])
            ->with('success', 'Đã xóa menu website.');
    }

    public function updateStatus(UpdateMenuStatusRequest $request, Menu $menu): RedirectResponse|JsonResponse
    {
        try {
            $menu = $this->menus->updateStatus(
                $request->user(),
                $menu,
                (bool) $request->validated('status'),
                $request,
            );
        } catch (MenuOperationException $exception) {
            return $this->mutationError($request, $exception);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Đã cập nhật trạng thái menu.',
                'menu' => ['id' => $menu->id, 'status' => $menu->status],
            ]);
        }

        return back()->with('success', 'Đã cập nhật trạng thái menu.');
    }

    public function reorder(ReorderMenusRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        try {
            $items = $this->menus->reorder(
                $request->user(),
                $validated['location'],
                $validated['parent_id'] ?? null,
                $validated['ordered_ids'],
                $request,
            );
        } catch (MenuOperationException $exception) {
            return $this->mutationError($request, $exception);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Đã cập nhật thứ tự menu.',
                'items' => $items->map(fn (Menu $menu): array => [
                    'id' => $menu->id,
                    'sort_order' => $menu->sort_order,
                ])->values(),
            ]);
        }

        return back()->with('success', 'Đã cập nhật thứ tự menu.');
    }

    private function mutationError(
        UpdateMenuStatusRequest|ReorderMenusRequest|MoveMenuRequest $request,
        MenuOperationException $exception,
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return back()->with('error', $exception->getMessage());
    }

    private function form(Menu $menu): View
    {
        $allMenus = Menu::query()->ordered()->get()->keyBy('id');
        $treeMetadata = $this->treeMetadata($allMenus);
        $subjectHeight = $menu->exists ? $this->menus->subtreeHeight($menu) : 1;
        $parents = $allMenus->filter(function (Menu $candidate) use ($menu, $subjectHeight, $treeMetadata): bool {
            if ($menu->exists && $candidate->is($menu)) {
                return false;
            }

            $metadata = $treeMetadata[$candidate->id] ?? null;

            if ($metadata === null
                || ($menu->exists && in_array((int) $menu->id, $metadata['ancestors'], true))) {
                return false;
            }

            return $metadata['depth'] + $subjectHeight <= (int) config('menus.max_depth', 4);
        })->map(function (Menu $candidate) use ($treeMetadata): Menu {
            $candidate->setAttribute('tree_depth', $treeMetadata[$candidate->id]['depth']);
            $candidate->setAttribute('breadcrumb', implode(' / ', $treeMetadata[$candidate->id]['labels']));

            return $candidate;
        })->sortBy([
            ['location', 'asc'],
            ['breadcrumb', 'asc'],
        ])->values();
        $routeUrls = collect(config('menus.routes', []))
            ->mapWithKeys(fn (array $metadata, string $name): array => [$name => route($name)])
            ->all();

        return view('admin.menus.form', [
            'menu' => $menu,
            'parents' => $parents,
            'locations' => config('menus.locations', []),
            'linkTypes' => config('menus.link_types', []),
            'targets' => config('menus.targets', []),
            'routes' => config('menus.routes', []),
            'routeUrls' => $routeUrls,
            'hasChildren' => $menu->exists && $menu->children()->exists(),
            'positionLocked' => $menu->exists,
            'currentDepth' => $menu->exists ? ($treeMetadata[$menu->id]['depth'] ?? $this->menus->depth($menu)) : 1,
            'maxDepth' => (int) config('menus.max_depth', 4),
            'subjectHeight' => $subjectHeight,
        ]);
    }

    /** @return array<string, callable> */
    private function treeRelations(): array
    {
        $relations = [];

        foreach ($this->descendantPaths() as $path) {
            $relations[$path] = function ($query): void {
                $query->withCount('children')->ordered();
            };
        }

        return $relations;
    }

    /** @return array<int, string> */
    private function descendantPaths(): array
    {
        $paths = [];
        $path = '';

        for ($depth = 2; $depth <= (int) config('menus.max_depth', 4); $depth++) {
            $path = $path === '' ? 'children' : $path.'.children';
            $paths[] = $path;
        }

        return $paths;
    }

    /**
     * @param Collection<int, Menu> $menus
     * @return array<int, array{depth:int, ancestors:array<int, int>, labels:array<int, string>}>
     */
    private function treeMetadata(Collection $menus): array
    {
        $resolved = [];
        $resolving = [];
        $resolve = function (int $id) use (&$resolve, &$resolved, &$resolving, $menus): ?array {
            if (array_key_exists($id, $resolved)) {
                return $resolved[$id];
            }

            if (isset($resolving[$id])) {
                return $resolved[$id] = null;
            }

            $menu = $menus->get($id);

            if (! $menu) {
                return $resolved[$id] = null;
            }

            $resolving[$id] = true;

            if ($menu->parent_id === null) {
                $metadata = ['depth' => 1, 'ancestors' => [], 'labels' => [$menu->name_vi]];
            } else {
                $parent = $menus->get((int) $menu->parent_id);
                $parentMetadata = $parent ? $resolve((int) $parent->id) : null;

                if ($parentMetadata === null || $parent->location !== $menu->location) {
                    unset($resolving[$id]);

                    return $resolved[$id] = null;
                }

                $metadata = [
                    'depth' => $parentMetadata['depth'] + 1,
                    'ancestors' => [...$parentMetadata['ancestors'], (int) $parent->id],
                    'labels' => [...$parentMetadata['labels'], $menu->name_vi],
                ];
            }

            unset($resolving[$id]);

            return $resolved[$id] = $metadata;
        };

        foreach ($menus as $menu) {
            $resolve((int) $menu->id);
        }

        return array_filter($resolved);
    }

    /**
     * @param Collection<int, Menu> $menus
     * @return array<int, array<string, mixed>>
     */
    private function moveCatalog(Collection $menus): array
    {
        $metadata = $this->treeMetadata($menus);

        return $menus->map(function (Menu $menu) use ($metadata): ?array {
            $item = $metadata[$menu->id] ?? null;

            if ($item === null) {
                return null;
            }

            $subtreeHeight = 1;

            foreach ($metadata as $candidate) {
                if (in_array((int) $menu->id, $candidate['ancestors'], true)) {
                    $subtreeHeight = max($subtreeHeight, $candidate['depth'] - $item['depth'] + 1);
                }
            }

            return [
                'id' => (int) $menu->id,
                'parent_id' => $menu->parent_id !== null ? (int) $menu->parent_id : null,
                'location' => $menu->location,
                'name' => $menu->name_vi,
                'depth' => $item['depth'],
                'ancestors' => $item['ancestors'],
                'breadcrumb' => implode(' / ', $item['labels']),
                'subtree_height' => $subtreeHeight,
            ];
        })->filter()->values()->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function validMoveParents(Collection $catalog, array $subject): Collection
    {
        return $catalog->filter(function (array $candidate) use ($subject): bool {
            if ($candidate['id'] === $subject['id']
                || in_array($subject['id'], $candidate['ancestors'], true)
                || $candidate['depth'] + $subject['subtree_height'] > (int) config('menus.max_depth', 4)) {
                return false;
            }

            return $subject['subtree_height'] === 1 || $candidate['location'] === $subject['location'];
        })->values();
    }
}
