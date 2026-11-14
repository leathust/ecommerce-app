<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\StorefrontProductPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class StorefrontCatalogService
{
    public function __construct(private readonly StorefrontProductPresenter $presenter) {}

    /** @param array<string, mixed> $filters
     *  @return array<string, mixed>
     */
    public function shop(array $filters, string $locale): array
    {
        $priceBounds = Product::query()
            ->published()
            ->selectRaw('COALESCE(MIN(price), 0) as minimum, COALESCE(MAX(price), 0) as maximum')
            ->first();
        $query = Product::query()
            ->published()
            ->with(['primaryImage', 'category', 'brand']);

        $this->applyFilters($query, $filters, $locale);
        $this->applySort($query, (string) ($filters['sort'] ?? 'newest'), $locale);

        $products = $query
            ->paginate((int) ($filters['per_page'] ?? 12))
            ->withQueryString();
        $products->setCollection($products->getCollection()->map(
            fn (Product $product): array => $this->presenter->present($product, $locale),
        ));

        return [
            'products' => $products,
            'categories' => $this->categoryFilters($locale),
            'brands' => $this->brandFilters($locale),
            'filters' => [
                'q' => (string) ($filters['q'] ?? ''),
                'category' => array_values($filters['category'] ?? []),
                'brand' => array_values($filters['brand'] ?? []),
                'min_price' => isset($filters['min_price']) ? (float) $filters['min_price'] : null,
                'max_price' => isset($filters['max_price']) ? (float) $filters['max_price'] : null,
                'sort' => (string) ($filters['sort'] ?? 'newest'),
                'per_page' => (int) ($filters['per_page'] ?? 12),
            ],
            'priceBounds' => [
                'min' => (float) ($priceBounds?->minimum ?? 0),
                'max' => (float) ($priceBounds?->maximum ?? 0),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Product $product, string $locale): array
    {
        $product->loadMissing([
            'category',
            'brand',
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
            'primaryImage',
        ]);

        $card = $this->presenter->present($product, $locale);
        $card['brand_name'] = $product->brand ? $this->localized($product->brand, 'name', $locale) : '';
        $card['images'] = $product->images->map(fn ($image): array => [
            'url' => $image->image_src,
            'alt' => trim((string) $image->alt_text) ?: $card['name'],
        ])->filter(fn (array $image): bool => $image['url'] !== null)->values()->all();
        if ($card['images'] === []) {
            $card['images'][] = ['url' => $card['image_url'], 'alt' => $card['image_alt']];
        }

        $related = Product::query()
            ->published()
            ->inStock()
            ->whereKeyNot($product->getKey())
            ->where('category_id', $product->category_id)
            ->with(['primaryImage', 'category', 'brand'])
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Product $item): array => $this->presenter->present($item, $locale))
            ->all();

        return ['product' => $card, 'relatedProducts' => $related];
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters, string $locale): void
    {
        $query
            ->when($filters['category'] ?? [], fn (Builder $builder, array $slugs): Builder => $builder->whereHas('category', fn (Builder $category): Builder => $category->whereIn('slug', $slugs)))
            ->when($filters['brand'] ?? [], fn (Builder $builder, array $slugs): Builder => $builder->whereHas('brand', fn (Builder $brand): Builder => $brand->whereIn('slug', $slugs)))
            ->when(isset($filters['min_price']), fn (Builder $builder): Builder => $builder->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn (Builder $builder): Builder => $builder->where('price', '<=', $filters['max_price']))
            ->when($filters['q'] ?? null, function (Builder $builder, string $term) use ($locale): void {
                $name = $locale === 'en' ? 'name_en' : 'name_vi';
                $fallback = $locale === 'en' ? 'name_vi' : 'name_en';
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
                $builder->where(function (Builder $search) use ($name, $fallback, $escaped): void {
                    $search->where($name, 'like', "%{$escaped}%")
                        ->orWhere($fallback, 'like', "%{$escaped}%")
                        ->orWhere('sku', 'like', "%{$escaped}%");
                });
            });
    }

    private function applySort(Builder $query, string $sort, string $locale): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            'name' => $query->orderBy($locale === 'en' ? 'name_en' : 'name_vi')->orderBy('id'),
            default => $query->orderByDesc('is_featured')->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function categoryFilters(string $locale): array
    {
        return Category::query()
            ->where('status', true)
            ->whereHas('products', fn (Builder $query): Builder => $query->published())
            ->withCount(['products as published_products_count' => fn (Builder $query): Builder => $query->published()])
            ->orderBy('sort_order')
            ->orderBy($locale === 'en' ? 'name_en' : 'name_vi')
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category): array => [
                'slug' => $category->slug,
                'name' => $this->localized($category, 'name', $locale),
                'count' => (int) $category->published_products_count,
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function brandFilters(string $locale): array
    {
        return Brand::query()
            ->where('status', true)
            ->whereHas('products', fn (Builder $query): Builder => $query->published())
            ->withCount(['products as published_products_count' => fn (Builder $query): Builder => $query->published()])
            ->orderBy($locale === 'en' ? 'name_en' : 'name_vi')
            ->orderBy('id')
            ->get()
            ->map(fn (Brand $brand): array => [
                'slug' => $brand->slug,
                'name' => $this->localized($brand, 'name', $locale),
                'count' => (int) $brand->published_products_count,
            ])->all();
    }

    private function localized(object $model, string $field, string $locale): string
    {
        $preferred = trim((string) $model->getAttribute($field.'_'.$locale));
        if ($preferred !== '') {
            return $preferred;
        }

        $fallback = trim((string) $model->getAttribute($field.'_vi'));

        return $fallback !== '' ? $fallback : trim((string) $model->getAttribute($field.'_en'));
    }
}
