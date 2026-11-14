<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductImage;

class StorefrontProductPresenter
{
    public function __construct(private readonly MoneyFormatter $moneyFormatter) {}

    /**
     * Build a query-free storefront card payload from an eagerly loaded product.
     *
     * @return array<string, mixed>
     */
    public function present(Product $product, ?string $locale = null): array
    {
        $locale = $locale === 'en' ? 'en' : 'vi';
        $name = $this->localized($product, 'name', $locale);
        $description = $this->localized($product, 'description', $locale);
        $image = $product->relationLoaded('primaryImage') ? $product->primaryImage : null;
        $category = $product->relationLoaded('category') ? $product->category : null;
        $quantity = max(0, (int) $product->quantity);

        return [
            'id' => $product->getKey(),
            'slug' => (string) $product->slug,
            'sku' => (string) $product->sku,
            'name' => $name,
            'description' => $description,
            'category_name' => $category ? $this->localizedModel($category, 'name', $locale) : '',
            'category_slug' => $category ? (string) $category->slug : '',
            'url' => route('products.show', ['storefrontProduct' => $product->slug]),
            'image_url' => $image?->image_src ?? asset('assets/frontend/images/product-placeholder.svg'),
            'image_alt' => $this->imageAlt($image, $name),
            'price' => (string) $product->price,
            'formatted_price' => $this->moneyFormatter->format($product->price),
            'quantity' => $quantity,
            'stock_status' => $quantity > 0 ? 'in_stock' : 'out_of_stock',
            'is_featured' => (bool) $product->is_featured,
            'badge' => $this->badge($product),
        ];
    }

    private function localized(Product $product, string $field, string $locale): string
    {
        $preferred = trim((string) $product->getAttribute($field.'_'.$locale));
        if ($preferred !== '') {
            return $preferred;
        }

        $fallback = trim((string) $product->getAttribute($field.'_vi'));
        if ($fallback !== '') {
            return $fallback;
        }

        return trim((string) $product->getAttribute($field.'_en'));
    }

    private function localizedModel(object $model, string $field, string $locale): string
    {
        $preferred = trim((string) $model->getAttribute($field.'_'.$locale));
        if ($preferred !== '') {
            return $preferred;
        }

        $fallback = trim((string) $model->getAttribute($field.'_vi'));

        return $fallback !== '' ? $fallback : trim((string) $model->getAttribute($field.'_en'));
    }

    private function imageAlt(?ProductImage $image, string $productName): string
    {
        $alt = trim((string) $image?->alt_text);

        return $alt !== '' ? $alt : $productName;
    }

    private function badge(Product $product): ?string
    {
        if ($product->is_featured) {
            return 'featured';
        }

        return $product->created_at?->gte(now()->subDays(30)) ? 'new' : null;
    }
}
