<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\StorefrontHomeService;
use Illuminate\Http\JsonResponse;

class StorefrontHomeProductController extends Controller
{
    public function __invoke(string $category, StorefrontHomeService $home): JsonResponse
    {
        $categoryModel = Category::query()
            ->where('slug', $category)
            ->where('status', true)
            ->whereHas('products', fn ($query) => $query->published())
            ->firstOrFail();
        $products = $home->trendingForCategory($categoryModel, app()->getLocale());

        return response()->json([
            'html' => view('frontend.home.trending-products', compact('products'))->render(),
            'category' => [
                'id' => $categoryModel->getKey(),
                'slug' => (string) $categoryModel->slug,
            ],
        ]);
    }
}
