<?php

namespace App\Services;

use App\Exceptions\MenuOperationException;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class MenuService
{
    private const MUTABLE_ATTRIBUTES = [
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
    ];

    public function __construct(private readonly AdminAuditService $audit) {}

    /** @param array<string, mixed> $attributes */
    public function createMenu(User $actor, array $attributes, ?Request $request = null): Menu
    {
        $this->assertPermission($actor, 'menus.create');
        $payload = $this->normalizeAttributes($attributes);

        $menu = DB::transaction(function () use ($actor, $payload, $request): Menu {
            $this->assertValidParent($payload['parent_id'], $payload['location']);
            $menu = Menu::create($payload);

            $this->audit->record(
                $actor,
                'menu.created',
                $menu,
                [],
                ['menu' => $this->snapshot($menu)],
                $request,
            );

            return $menu;
        });

        $this->forgetPublishedCache();

        return $menu;
    }

    /** @param array<string, mixed> $attributes */
    public function updateMenu(User $actor, Menu $menu, array $attributes, ?Request $request = null): Menu
    {
        $this->assertPermission($actor, 'menus.update');

        $updated = DB::transaction(function () use ($actor, $menu, $attributes, $request): Menu {
            $lockedMenu = Menu::query()->whereKey($menu)->lockForUpdate()->firstOrFail();
            $current = Arr::only($lockedMenu->getAttributes(), self::MUTABLE_ATTRIBUTES);
            $payload = $this->normalizeAttributes(array_replace($current, Arr::only($attributes, self::MUTABLE_ATTRIBUTES)));
            if ((int) ($payload['parent_id'] ?? 0) !== (int) ($lockedMenu->parent_id ?? 0)
                || $payload['location'] !== $lockedMenu->location) {
                throw MenuOperationException::invalid('Hãy dùng chức năng Di chuyển để thay đổi menu cha hoặc vị trí header/footer.');
            }

            $this->assertValidParent($payload['parent_id'], $payload['location'], $lockedMenu);
            $before = $this->snapshot($lockedMenu);
            $lockedMenu->update($payload);

            $this->audit->record(
                $actor,
                'menu.updated',
                $lockedMenu,
                ['menu' => $before],
                ['menu' => $this->snapshot($lockedMenu)],
                $request,
            );

            return $lockedMenu;
        });

        $this->forgetPublishedCache();

        return $updated;
    }

    public function updateStatus(User $actor, Menu $menu, bool $status, ?Request $request = null): Menu
    {
        $this->assertPermission($actor, 'menus.update');

        $updated = DB::transaction(function () use ($actor, $menu, $status, $request): Menu {
            $lockedMenu = Menu::query()->whereKey($menu)->lockForUpdate()->firstOrFail();
            $before = $lockedMenu->status;
            $lockedMenu->update(['status' => $status]);

            $this->audit->record(
                $actor,
                'menu.status_updated',
                $lockedMenu,
                ['status' => $before],
                ['status' => $lockedMenu->status],
                $request,
            );

            return $lockedMenu;
        });

        $this->forgetPublishedCache();

        return $updated;
    }

    public function moveMenu(
        User $actor,
        Menu $menu,
        ?int $parentId,
        string $location,
        ?int $sourceParentId,
        string $sourceLocation,
        ?Request $request = null,
    ): Menu {
        $this->assertPermission($actor, 'menus.update');
        $this->assertKnownLocation($location);
        $changed = false;

        $moved = DB::transaction(function () use (
            $actor,
            $menu,
            $parentId,
            $location,
            $sourceParentId,
            $sourceLocation,
            $request,
            &$changed,
        ): Menu {
            $lockedMenu = Menu::query()->whereKey($menu)->lockForUpdate()->firstOrFail();

            if ((int) ($lockedMenu->parent_id ?? 0) !== (int) ($sourceParentId ?? 0)
                || $lockedMenu->location !== $sourceLocation) {
                throw MenuOperationException::invalid('Vị trí menu đã thay đổi bởi thao tác khác. Hãy tải lại trang trước khi di chuyển.');
            }

            $descendants = $this->lockDescendants($lockedMenu);
            $this->lockAncestorChain($lockedMenu->parent_id);
            $this->lockAncestorChain($parentId);

            if ($descendants->isNotEmpty() && $location !== $lockedMenu->location) {
                throw MenuOperationException::invalid('Không thể đổi vị trí header/footer cho menu đang có nhánh con.');
            }

            $this->assertValidParent($parentId, $location, $lockedMenu);

            if ((int) ($lockedMenu->parent_id ?? 0) === (int) ($parentId ?? 0)
                && $lockedMenu->location === $location) {
                return $lockedMenu;
            }

            Menu::query()
                ->where('location', $lockedMenu->location)
                ->where('parent_id', $lockedMenu->parent_id)
                ->lockForUpdate()
                ->get();
            $destinationSiblings = Menu::query()
                ->where('location', $location)
                ->where('parent_id', $parentId)
                ->lockForUpdate()
                ->get();
            $step = max(1, (int) config('menus.sort_step', 10));
            $sortOrder = ((int) $destinationSiblings->where('id', '!=', $lockedMenu->id)->max('sort_order')) + $step;
            $before = [
                'parent_id' => $lockedMenu->parent_id,
                'location' => $lockedMenu->location,
                'depth' => $this->depth($lockedMenu),
                'sort_order' => $lockedMenu->sort_order,
            ];

            $lockedMenu->update([
                'parent_id' => $parentId,
                'location' => $location,
                'sort_order' => $sortOrder,
            ]);
            $lockedMenu->refresh();
            $after = [
                'parent_id' => $lockedMenu->parent_id,
                'location' => $lockedMenu->location,
                'depth' => $this->depth($lockedMenu),
                'sort_order' => $lockedMenu->sort_order,
            ];

            $this->audit->record(
                $actor,
                'menu.moved',
                $lockedMenu,
                ['menu' => $before],
                ['menu' => $after],
                $request,
            );
            $changed = true;

            return $lockedMenu;
        });

        if ($changed) {
            $this->forgetPublishedCache();
        }

        return $moved;
    }

    public function deleteMenu(User $actor, Menu $menu, ?Request $request = null): void
    {
        $this->assertPermission($actor, 'menus.delete');

        DB::transaction(function () use ($actor, $menu, $request): void {
            $lockedMenu = Menu::query()->whereKey($menu)->lockForUpdate()->firstOrFail();

            if (Menu::query()->where('parent_id', $lockedMenu->id)->lockForUpdate()->exists()) {
                throw MenuOperationException::hasChildren();
            }

            $this->audit->record(
                $actor,
                'menu.deleted',
                $lockedMenu,
                ['menu' => $this->snapshot($lockedMenu)],
                ['outcome' => 'deleted'],
                $request,
            );
            $lockedMenu->delete();
        });

        $this->forgetPublishedCache();
    }

    /**
     * @param array<int, int|string> $orderedIds
     * @return Collection<int, Menu>
     */
    public function reorder(
        User $actor,
        string $location,
        ?int $parentId,
        array $orderedIds,
        ?Request $request = null,
    ): Collection {
        $this->assertPermission($actor, 'menus.update');
        $this->assertKnownLocation($location);
        $ids = collect($orderedIds)->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->count() !== count($orderedIds) || $ids->contains(fn (int $id): bool => $id <= 0)) {
            throw MenuOperationException::invalid('Danh sách sắp xếp menu không hợp lệ.');
        }

        $result = DB::transaction(function () use ($actor, $location, $parentId, $ids, $request): array {
            if ($parentId !== null) {
                $this->assertValidParent($parentId, $location);
            }

            $siblings = Menu::query()
                ->where('location', $location)
                ->where('parent_id', $parentId)
                ->lockForUpdate()
                ->get();

            if ($siblings->pluck('id')->sort()->values()->all() !== $ids->sort()->values()->all()) {
                throw MenuOperationException::invalid('Danh sách sắp xếp không khớp với các menu cùng cấp.');
            }

            $step = max(1, (int) config('menus.sort_step', 10));
            $changes = [];

            foreach ($ids as $index => $id) {
                $item = $siblings->firstWhere('id', $id);
                $nextOrder = ($index + 1) * $step;

                if ($item->sort_order === $nextOrder) {
                    continue;
                }

                $changes[] = ['id' => $id, 'before' => $item->sort_order, 'after' => $nextOrder];
                $item->update(['sort_order' => $nextOrder]);
            }

            if ($changes !== []) {
                $this->audit->recordSystemEvent(
                    $actor,
                    'menu.reordered',
                    Menu::class,
                    ['location' => $location, 'parent_id' => $parentId, 'items' => array_map(
                        fn (array $change): array => ['id' => $change['id'], 'sort_order' => $change['before']],
                        $changes,
                    )],
                    ['location' => $location, 'parent_id' => $parentId, 'items' => array_map(
                        fn (array $change): array => ['id' => $change['id'], 'sort_order' => $change['after']],
                        $changes,
                    )],
                    $request,
                );
            }

            return ['changed' => $changes !== [], 'items' => $siblings->fresh()->sortBy('sort_order')->values()];
        });

        if ($result['changed']) {
            $this->forgetPublishedCache();
        }

        return $result['items'];
    }

    /** @return array<int, array<string, mixed>> */
    public function published(
        string $location,
        ?string $locale = null,
        ?string $currentRouteName = null,
        ?string $currentPath = null,
    ): array {
        $this->assertKnownLocation($location);
        $locale = $this->normalizeLocale($locale);
        $items = Cache::rememberForever($this->cacheKey($location, $locale), function () use ($location, $locale): array {
            return Menu::query()
                ->forLocation($location)
                ->roots()
                ->active()
                ->ordered()
                ->with($this->publishedRelations())
                ->get()
                ->map(fn (Menu $menu): ?array => $this->publishedItem($menu, $locale, 1, []))
                ->filter()
                ->values()
                ->all();
        });

        $currentRouteName ??= Route::currentRouteName();
        $currentPath ??= request()->getPathInfo();

        return array_map(
            fn (array $item): array => $this->applyActiveState($item, $currentRouteName, $currentPath),
            $items,
        );
    }

    public function forgetPublishedCache(): void
    {
        foreach (array_keys(config('menus.locations', [])) as $location) {
            foreach (config('menus.locales', ['vi', 'en']) as $locale) {
                Cache::forget($this->cacheKey($location, $locale));
            }
        }
    }

    /** @param array<string, mixed> $attributes
     *  @return array<string, mixed>
     */
    private function normalizeAttributes(array $attributes): array
    {
        $missing = array_diff(['name_vi', 'name_en'], array_keys($attributes));

        if ($missing !== []) {
            throw MenuOperationException::invalid('Menu phải có tên tiếng Việt và tiếng Anh.');
        }

        $location = trim((string) ($attributes['location'] ?? 'header'));
        $linkType = trim((string) ($attributes['link_type'] ?? 'url'));
        $target = trim((string) ($attributes['target'] ?? '_self'));
        $this->assertKnownLocation($location);

        if (! array_key_exists($linkType, config('menus.link_types', []))) {
            throw MenuOperationException::invalid('Kiểu liên kết menu không hợp lệ.');
        }

        if (! array_key_exists($target, config('menus.targets', []))) {
            throw MenuOperationException::invalid('Cách mở liên kết menu không hợp lệ.');
        }

        $nameVi = trim((string) $attributes['name_vi']);
        $nameEn = trim((string) $attributes['name_en']);

        if ($nameVi === '' || $nameEn === '') {
            throw MenuOperationException::invalid('Tên menu tiếng Việt và tiếng Anh không được để trống.');
        }

        $routeName = filled($attributes['route_name'] ?? null) ? trim((string) $attributes['route_name']) : null;
        $url = filled($attributes['url'] ?? null) ? trim((string) $attributes['url']) : null;

        if ($linkType === 'route') {
            if ($routeName === null || ! $this->isAllowedRoute($routeName)) {
                throw MenuOperationException::invalid('Route menu không nằm trong danh sách được phép.');
            }

            $url = null;
        } elseif ($linkType === 'url') {
            if ($url === null) {
                throw MenuOperationException::invalid('URL menu không được để trống.');
            }

            $this->assertSafeUrl($url);
            $routeName = null;
        } else {
            $routeName = null;
            $url = null;
            $target = '_self';
        }

        $parentId = $attributes['parent_id'] ?? null;
        $parentId = filled($parentId) ? (int) $parentId : null;
        $sortOrder = (int) ($attributes['sort_order'] ?? 0);

        if ($parentId !== null && $parentId <= 0) {
            throw MenuOperationException::invalid('Menu cha không hợp lệ.');
        }

        if ($sortOrder < 0) {
            throw MenuOperationException::invalid('Thứ tự menu không được âm.');
        }

        $status = filter_var($attributes['status'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($status === null) {
            throw MenuOperationException::invalid('Trạng thái menu không hợp lệ.');
        }

        return [
            'parent_id' => $parentId,
            'name_vi' => $nameVi,
            'name_en' => $nameEn,
            'link_type' => $linkType,
            'route_name' => $routeName,
            'url' => $url,
            'target' => $target,
            'location' => $location,
            'sort_order' => $sortOrder,
            'status' => $status,
        ];
    }

    public function depth(Menu $menu): int
    {
        return count($this->ancestorIds($menu)) + 1;
    }

    /** @return array<int, int> */
    public function ancestorIds(Menu $menu): array
    {
        $ancestors = [];
        $visited = [(int) $menu->id => true];
        $parentId = $menu->parent_id !== null ? (int) $menu->parent_id : null;
        $maxDepth = $this->maxDepth();

        while ($parentId !== null) {
            if (isset($visited[$parentId])) {
                throw MenuOperationException::invalid('Cây menu chứa vòng lặp không hợp lệ.');
            }

            $visited[$parentId] = true;
            $ancestors[] = $parentId;

            if (count($ancestors) >= $maxDepth) {
                throw MenuOperationException::invalid('Cây menu vượt quá độ sâu tối đa '.$maxDepth.' cấp.');
            }

            $parent = Menu::query()->find($parentId);

            if (! $parent) {
                throw MenuOperationException::invalid('Cây menu tham chiếu tới menu cha không tồn tại.');
            }

            $parentId = $parent->parent_id !== null ? (int) $parent->parent_id : null;
        }

        return $ancestors;
    }

    public function subtreeHeight(Menu $menu): int
    {
        $visited = [];

        return $this->subtreeHeightFrom($menu, $visited, 1);
    }

    public function assertParentAllowed(?int $parentId, string $location, ?Menu $subject = null): void
    {
        $this->assertKnownLocation($location);
        $this->assertValidParent($parentId, $location, $subject);
    }

    private function assertValidParent(?int $parentId, string $location, ?Menu $subject = null): void
    {
        if ($parentId === null) {
            if ($subject !== null && $this->subtreeHeight($subject) > $this->maxDepth()) {
                throw MenuOperationException::invalid('Nhánh menu vượt quá độ sâu tối đa '.$this->maxDepth().' cấp.');
            }

            return;
        }

        if ($subject?->id === $parentId) {
            throw MenuOperationException::invalid('Menu không thể là cha của chính nó.');
        }

        $parent = Menu::query()->whereKey($parentId)->lockForUpdate()->first();

        if (! $parent || $parent->location !== $location) {
            throw MenuOperationException::invalid('Menu cha không tồn tại hoặc thuộc vị trí khác.');
        }

        $parentAncestors = $this->ancestorIds($parent);

        if ($subject !== null && in_array((int) $subject->id, $parentAncestors, true)) {
            throw MenuOperationException::invalid('Không thể chọn một menu hậu duệ làm menu cha.');
        }

        $subjectHeight = $subject !== null ? $this->subtreeHeight($subject) : 1;
        $nextDeepestLevel = count($parentAncestors) + 1 + $subjectHeight;

        if ($nextDeepestLevel > $this->maxDepth()) {
            throw MenuOperationException::invalid('Nhánh menu sẽ vượt quá độ sâu tối đa '.$this->maxDepth().' cấp.');
        }
    }

    /** @return array<string, callable> */
    private function publishedRelations(): array
    {
        $relations = [];
        $path = '';

        for ($level = 2; $level <= $this->maxDepth(); $level++) {
            $path = $path === '' ? 'children' : $path.'.children';
            $relations[$path] = fn ($query) => $query->active()->ordered();
        }

        return $relations;
    }

    /** @param array<int, bool> $visited */
    private function subtreeHeightFrom(Menu $menu, array &$visited, int $height): int
    {
        $menuId = (int) $menu->id;

        if (isset($visited[$menuId])) {
            throw MenuOperationException::invalid('Cây menu chứa vòng lặp không hợp lệ.');
        }

        if ($height > $this->maxDepth()) {
            throw MenuOperationException::invalid('Nhánh menu vượt quá độ sâu tối đa '.$this->maxDepth().' cấp.');
        }

        $visited[$menuId] = true;
        $maximum = 1;
        $children = Menu::query()->where('parent_id', $menuId)->get();

        foreach ($children as $child) {
            $maximum = max($maximum, 1 + $this->subtreeHeightFrom($child, $visited, $height + 1));
        }

        unset($visited[$menuId]);

        return $maximum;
    }

    private function maxDepth(): int
    {
        return max(1, (int) config('menus.max_depth', 4));
    }

    /** @return Collection<int, Menu> */
    private function lockDescendants(Menu $menu): Collection
    {
        $descendants = collect();
        $frontier = collect([(int) $menu->id]);
        $visited = [(int) $menu->id => true];

        for ($level = 1; $level < $this->maxDepth() && $frontier->isNotEmpty(); $level++) {
            $children = Menu::query()
                ->whereIn('parent_id', $frontier)
                ->lockForUpdate()
                ->get();

            foreach ($children as $child) {
                if (isset($visited[$child->id])) {
                    throw MenuOperationException::invalid('Cây menu chứa vòng lặp không hợp lệ.');
                }

                $visited[$child->id] = true;
            }

            $descendants = $descendants->concat($children);
            $frontier = $children->pluck('id');
        }

        if ($frontier->isNotEmpty()
            && Menu::query()->whereIn('parent_id', $frontier)->exists()) {
            throw MenuOperationException::invalid('Nhánh menu vượt quá độ sâu tối đa '.$this->maxDepth().' cấp.');
        }

        return $descendants;
    }

    private function lockAncestorChain(?int $parentId): void
    {
        $visited = [];

        while ($parentId !== null) {
            if (isset($visited[$parentId])) {
                throw MenuOperationException::invalid('Cây menu chứa vòng lặp không hợp lệ.');
            }

            $visited[$parentId] = true;
            $parent = Menu::query()->whereKey($parentId)->lockForUpdate()->first();

            if (! $parent) {
                throw MenuOperationException::invalid('Cây menu tham chiếu tới menu cha không tồn tại.');
            }

            $parentId = $parent->parent_id !== null ? (int) $parent->parent_id : null;
        }
    }

    private function assertKnownLocation(string $location): void
    {
        if (! array_key_exists($location, config('menus.locations', []))) {
            throw MenuOperationException::invalid('Vị trí menu không hợp lệ.');
        }
    }

    private function assertSafeUrl(string $url): void
    {
        if (preg_match('/[\x00-\x1F\x7F]/u', $url) || str_contains($url, '\\') || str_starts_with($url, '//')) {
            throw MenuOperationException::invalid('URL menu không an toàn.');
        }

        if (str_starts_with($url, '/')) {
            $this->assertPublicInternalPath((string) parse_url($url, PHP_URL_PATH));

            return;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw MenuOperationException::invalid('URL menu không hợp lệ.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, config('menus.allowed_url_schemes', ['http', 'https']), true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            throw MenuOperationException::invalid('URL menu không sử dụng scheme hoặc thông tin xác thực được phép.');
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($appHost !== '' && strtolower((string) $parts['host']) === $appHost) {
            $this->assertPublicInternalPath((string) ($parts['path'] ?? '/'));
        }
    }

    private function assertPublicInternalPath(string $path): void
    {
        $decodedPath = strtolower(rawurldecode(rawurldecode($path)));

        if (preg_match('/[\x00-\x1F\x7F]/u', $decodedPath)) {
            throw MenuOperationException::invalid('Đường dẫn menu không an toàn.');
        }

        $decodedPath = str_replace('\\', '/', $decodedPath);
        $segments = [];

        foreach (explode('/', preg_replace('#/+#', '/', $decodedPath)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
            } else {
                $segments[] = $segment;
            }
        }

        $decodedPath = '/'.implode('/', $segments);

        foreach (config('menus.blocked_internal_prefixes', ['/admin']) as $prefix) {
            $prefix = '/'.trim(strtolower((string) $prefix), '/');

            if ($decodedPath === $prefix || str_starts_with($decodedPath, $prefix.'/')) {
                throw MenuOperationException::invalid('Menu không được liên kết tới khu vực quản trị.');
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function publishedItem(Menu $menu, string $locale, int $depth, array $visited): ?array
    {
        $menuId = (int) $menu->id;

        if ($depth > $this->maxDepth() || isset($visited[$menuId])) {
            return null;
        }

        $visited[$menuId] = true;
        $loadedChildren = $menu->relationLoaded('children')
            ? $menu->getRelation('children')
            : collect();
        $children = $depth < $this->maxDepth()
            ? $loadedChildren
                ->map(fn (Menu $child): ?array => $this->publishedItem($child, $locale, $depth + 1, $visited))
                ->filter()
                ->values()
                ->all()
            : [];
        $label = $menu->localizedName($locale);

        if ($label === '') {
            return null;
        }

        try {
            $href = match ($menu->link_type) {
                'route' => $this->resolveRoute($menu->route_name),
                'url' => $this->resolveCustomUrl($menu->url),
                'none' => null,
                default => throw MenuOperationException::invalid('Kiểu liên kết menu không hợp lệ.'),
            };
        } catch (MenuOperationException) {
            return null;
        }

        if ($menu->link_type === 'none' && $children === []) {
            return null;
        }

        return [
            'id' => $menu->id,
            'label' => $label,
            'href' => $href,
            'target' => $menu->target,
            'rel' => $menu->target === '_blank' ? 'noopener noreferrer' : null,
            'link_type' => $menu->link_type,
            'route_name' => $menu->route_name,
            'url' => $menu->url,
            'depth' => $depth,
            'children' => $children,
        ];
    }

    private function resolveRoute(?string $routeName): string
    {
        if ($routeName === null || ! $this->isAllowedRoute($routeName)) {
            throw MenuOperationException::invalid('Route menu không hợp lệ.');
        }

        return route($routeName);
    }

    private function resolveCustomUrl(?string $url): string
    {
        if ($url === null) {
            throw MenuOperationException::invalid('URL menu không hợp lệ.');
        }

        $this->assertSafeUrl($url);

        return $url;
    }

    private function isAllowedRoute(string $routeName): bool
    {
        $route = Route::getRoutes()->getByName($routeName);

        return array_key_exists($routeName, config('menus.routes', []))
            && $route !== null
            && $route->parameterNames() === []
            && ! str_starts_with($routeName, 'admin.');
    }

    /** @param array<string, mixed> $item
     *  @return array<string, mixed>
     */
    private function applyActiveState(array $item, ?string $routeName, string $path): array
    {
        $item['children'] = array_map(
            fn (array $child): array => $this->applyActiveState($child, $routeName, $path),
            $item['children'],
        );
        $selfActive = $item['link_type'] === 'route'
            ? $item['route_name'] === $routeName
            : ($item['link_type'] === 'url' && $this->isInternalUrlActive($item['url'], $path));
        $item['self_active'] = $selfActive;
        $item['active'] = $selfActive || collect($item['children'])->contains('active', true);

        return $item;
    }

    private function isInternalUrlActive(?string $url, string $requestPath): bool
    {
        if ($url === null) {
            return false;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($host !== '' && $host !== $appHost) {
            return false;
        }

        $menuPath = $this->normalizedPath((string) ($parts['path'] ?? '/'));

        return $menuPath === $this->normalizedPath($requestPath);
    }

    private function normalizedPath(string $path): string
    {
        $path = '/'.trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function normalizeLocale(?string $locale): string
    {
        $locale ??= app()->getLocale();

        return in_array($locale, config('menus.locales', ['vi', 'en']), true)
            ? $locale
            : (string) config('menus.fallback_locale', 'vi');
    }

    private function cacheKey(string $location, string $locale): string
    {
        return implode('.', [config('menus.cache.prefix', 'menus.published.v2'), $location, $locale]);
    }

    /** @return array<string, mixed> */
    private function snapshot(Menu $menu): array
    {
        return Arr::only($menu->getAttributes(), [
            'id',
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
        ]);
    }

    private function assertPermission(User $actor, string $permission): void
    {
        if (! $actor->hasPermission($permission)) {
            throw MenuOperationException::forbidden();
        }
    }
}
