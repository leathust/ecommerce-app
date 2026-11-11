<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_seeder_creates_sample_catalog_and_can_run_repeatedly(): void
    {
        Storage::fake('product_images');
        $this->seed(ProductSeeder::class);

        $this->assertSame(60, Product::where('sku', 'like', 'DEMO-%')->count());
        $this->assertSame(5, Category::whereIn('slug', ['dien-thoai', 'may-tinh-bang', 'laptop', 'phu-kien', 'thiet-bi-am-thanh'])->count());
        $this->assertSame(6, Brand::whereIn('slug', ['apple', 'samsung', 'xiaomi', 'sony', 'dell', 'asus'])->count());
        $this->assertDatabaseHas('products', ['sku' => 'DEMO-060', 'is_featured' => true, 'status' => false]);
        $this->assertSame(60, ProductImage::whereHas('product', fn ($query) => $query->where('sku', 'like', 'DEMO-%'))->count());
        $first = Product::where('sku', 'DEMO-001')->firstOrFail();
        $firstImage = $first->images()->firstOrFail();
        $this->assertTrue($firstImage->is_primary);
        Storage::disk('product_images')->assertExists($firstImage->image_url);
        $this->assertSame('image/png', Storage::disk('product_images')->mimeType($firstImage->image_url));

        $this->seed(ProductSeeder::class);

        $this->assertSame(60, Product::where('sku', 'like', 'DEMO-%')->count());
        $this->assertSame(60, ProductImage::count());
    }
}
