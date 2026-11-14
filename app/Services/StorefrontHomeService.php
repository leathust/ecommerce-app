<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\HomePageData;
use App\Support\StorefrontProductPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StorefrontHomeService
{
    private const CACHE_PREFIX = 'storefront.home.v5';
    private const PRODUCT_POOL_LIMIT = 50;

    public function __construct(
        private readonly StorefrontProductPresenter $products,
        private readonly HomeBannerService $banners,
    ) {}

    public function home(?string $locale = null): HomePageData
    {
        $locale = $locale === 'en' ? 'en' : 'vi';

        return Cache::rememberForever($this->cacheKey($locale), fn (): HomePageData => $this->build($locale));
    }

    public function forget(): void
    {
        foreach ($this->supportedLocales() as $locale) {
            Cache::forget($this->cacheKey($locale));
        }
    }

    private function build(string $locale): HomePageData
    {
        $pool = Product::query()
            ->published()
            ->with(['primaryImage', 'category', 'brand'])
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PRODUCT_POOL_LIMIT)
            ->get();

        $hero = $pool
            ->filter(fn (Product $product): bool => $product->primaryImage !== null)
            ->take(6);
        $categories = $this->categories($locale);
        $banners = $this->banners->published($locale);
        $trending = $categories->isNotEmpty()
            ? $this->categoryProducts($categories->first())
            : $pool->take(0);
        $trendingIds = $trending->modelKeys();
        $suggested = $pool
            ->filter(fn (Product $product): bool => (int) $product->quantity > 0 && ! in_array($product->getKey(), $trendingIds, true))
            ->take(4);
        return new HomePageData(
            locale: $locale,
            heroProducts: $this->presentProducts($hero, $locale),
            trendingProducts: $this->presentProducts($trending, $locale),
            categoryTabs: $categories->map(fn (Category $category): array => $this->categoryPayload($category, $locale, false))->values()->all(),
            suggestedProducts: $this->presentProducts($suggested, $locale),
            categories: $categories->map(fn (Category $category): array => $this->categoryPayload($category, $locale, true))->values()->all(),
            brands: $this->brands($locale)->map(fn (Brand $brand): array => $this->brandPayload($brand, $locale))->values()->all(),
            heroBanners: $banners['hero'],
            discountBanners: $banners['discount'],
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function trendingForCategory(Category $category, ?string $locale = null): array
    {
        $locale = $locale === 'en' ? 'en' : 'vi';

        return $this->presentProducts($this->categoryProducts($category), $locale);
    }

    /** @return Collection<int, Product> */
    private function categoryProducts(Category $category): Collection
    {
        return Product::query()
            ->published()
            ->inStock()
            ->where('category_id', $category->getKey())
            ->with(['primaryImage', 'category', 'brand'])
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    /** @param Collection<int, Product> $products
     *  @return array<int, array<string, mixed>>
     */
    private function presentProducts(Collection $products, string $locale): array
    {
        return $products
            ->map(fn (Product $product): array => $this->products->present($product, $locale))
            ->values()
            ->all();
    }

    /** @return Collection<int, Category> */
    private function categories(string $locale): Collection
    {
        $nameColumn = $locale === 'en' ? 'name_en' : 'name_vi';

        return Category::query()
            ->where('status', true)
            ->whereHas('products', fn ($query) => $query->published())
            ->withCount(['products as published_products_count' => fn ($query) => $query->published()])
            ->orderBy('sort_order')
            ->orderBy($nameColumn)
            ->orderBy('id')
            ->limit(5)
            ->get();
    }

    /** @return Collection<int, Brand> */
    private function brands(string $locale): Collection
    {
        $nameColumn = $locale === 'en' ? 'name_en' : 'name_vi';

        return Brand::query()
            ->where('status', true)
            ->whereHas('products', fn ($query) => $query->published())
            ->withCount(['products as published_products_count' => fn ($query) => $query->published()])
            ->orderBy($nameColumn)
            ->orderBy('id')
            ->limit(6)
            ->get();
    }

    /** @return array<string, mixed> */
    private function categoryPayload(Category $category, string $locale, bool $withImage): array
    {
        $payload = [
            'id' => $category->getKey(),
            'slug' => (string) $category->slug,
            'name' => $this->localized($category, 'name', $locale),
            'product_count' => (int) $category->published_products_count,
            'url' => route('shop', ['category' => $category->slug]),
        ];

        if ($withImage) {
            $payload['image_url'] = $category->image_src ?? asset('assets/frontend/images/product-placeholder.svg');
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function brandPayload(Brand $brand, string $locale): array
    {
        return [
            'id' => $brand->getKey(),
            'slug' => (string) $brand->slug,
            'name' => $this->localized($brand, 'name', $locale),
            'product_count' => (int) $brand->published_products_count,
            'image_url' => $brand->image_src,
            'url' => route('shop', ['brand' => $brand->slug]),
        ];
    }

    private function localized(Category|Brand $model, string $field, string $locale): string
    {
        $preferred = trim((string) $model->getAttribute($field.'_'.$locale));
        if ($preferred !== '') {
            return $preferred;
        }

        $fallback = trim((string) $model->getAttribute($field.'_vi'));

        return $fallback !== '' ? $fallback : trim((string) $model->getAttribute($field.'_en'));
    }

    /** @return array<int, string> */
    private function supportedLocales(): array
    {
        $locales = array_values(array_filter(
            config('menus.locales', ['vi', 'en']),
            static fn ($locale): bool => in_array($locale, ['vi', 'en'], true),
        ));

        return $locales !== [] ? array_unique($locales) : ['vi', 'en'];
    }

    private function cacheKey(string $locale): string
    {
        return self::CACHE_PREFIX.'.'.$locale;
    }
}
