<?php

namespace App\Support;

final readonly class HomePageData
{
    /**
     * @param  array<int, array<string, mixed>>  $heroProducts
     * @param  array<int, array<string, mixed>>  $trendingProducts
     * @param  array<int, array<string, mixed>>  $categoryTabs
     * @param  array<int, array<string, mixed>>  $suggestedProducts
     * @param  array<int, array<string, mixed>>  $categories
     * @param  array<int, array<string, mixed>>  $brands
     * @param  array<int, array<string, mixed>>  $heroBanners
     * @param  array<int, array<string, mixed>>  $discountBanners
     */
    public function __construct(
        public string $locale,
        public array $heroProducts,
        public array $trendingProducts,
        public array $categoryTabs,
        public array $suggestedProducts,
        public array $categories,
        public array $brands,
        public array $heroBanners,
        public array $discountBanners,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'heroProducts' => $this->heroProducts,
            'trendingProducts' => $this->trendingProducts,
            'categoryTabs' => $this->categoryTabs,
            'suggestedProducts' => $this->suggestedProducts,
            'categories' => $this->categories,
            'brands' => $this->brands,
            'heroBanners' => $this->heroBanners,
            'discountBanners' => $this->discountBanners,
        ];
    }
}
