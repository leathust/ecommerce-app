<?php

namespace App\Support;

use App\Services\MenuService;
use Illuminate\Support\Facades\Route;

class StorefrontMenuPresenter
{
    public function __construct(private readonly MenuService $menus) {}

    /** @return array{header: array<int, array<string, mixed>>, footer: array<int, array<string, mixed>>, fallback: array<string, bool>} */
    public function navigation(
        ?string $locale = null,
        ?string $currentRouteName = null,
        ?string $currentPath = null,
    ): array {
        $locale = $this->normalizeLocale($locale);
        $header = $this->menus->published('header', $locale, $currentRouteName, $currentPath);
        $footer = $this->menus->published('footer', $locale, $currentRouteName, $currentPath);
        $fallbackEnabled = (bool) config('menus.fallback_enabled', false);
        $headerFallback = $fallbackEnabled && $header === [];
        $footerFallback = $fallbackEnabled && $footer === [];

        return [
            'header' => $headerFallback ? $this->fallback('header', $locale, $currentRouteName) : $header,
            'footer' => $footerFallback ? $this->fallback('footer', $locale, $currentRouteName) : $footer,
            'fallback' => ['header' => $headerFallback, 'footer' => $footerFallback],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function fallback(string $location, string $locale, ?string $currentRouteName): array
    {
        return collect(config("menus.defaults.{$location}", []))
            ->map(fn (array $item, int $index): array => $this->fallbackItem(
                $item,
                $locale,
                $currentRouteName,
                $location.'-'.$index,
                1,
            ))
            ->all();
    }

    /** @param array<string, mixed> $item
     *  @return array<string, mixed>
     */
    private function fallbackItem(array $item, string $locale, ?string $currentRouteName, string $id, int $depth): array
    {
        $alternate = $locale === 'vi' ? 'en' : 'vi';
        $routeName = $item['route_name'] ?? null;
        $children = collect($item['children'] ?? [])
            ->map(fn (array $child, int $index): array => $this->fallbackItem(
                $child,
                $locale,
                $currentRouteName,
                $id.'-'.$index,
                $depth + 1,
            ))
            ->all();
        $selfActive = is_string($routeName) && $routeName === $currentRouteName;

        return [
            'id' => 'fallback-'.$id,
            'label' => (string) (($item['label_'.$locale] ?? '') ?: ($item['label_'.$alternate] ?? '')),
            'href' => is_string($routeName) && Route::has($routeName) ? route($routeName) : null,
            'target' => '_self',
            'rel' => null,
            'link_type' => is_string($routeName) ? 'route' : 'none',
            'route_name' => $routeName,
            'url' => null,
            'depth' => $depth,
            'children' => $children,
            'self_active' => $selfActive,
            'active' => $selfActive || collect($children)->contains('active', true),
        ];
    }

    private function normalizeLocale(?string $locale): string
    {
        return in_array($locale, config('menus.locales', ['vi', 'en']), true)
            ? (string) $locale
            : (string) config('menus.fallback_locale', 'vi');
    }
}
