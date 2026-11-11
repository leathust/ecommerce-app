<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductSeeder extends Seeder
{
    private const PRODUCT_COUNT = 60;

    public function run(): void
    {
        DB::transaction(function (): void {
            $categories = collect([
                ['name_vi' => 'Điện thoại', 'name_en' => 'Phones', 'slug' => 'dien-thoai'],
                ['name_vi' => 'Máy tính bảng', 'name_en' => 'Tablets', 'slug' => 'may-tinh-bang'],
                ['name_vi' => 'Laptop', 'name_en' => 'Laptops', 'slug' => 'laptop'],
                ['name_vi' => 'Phụ kiện', 'name_en' => 'Accessories', 'slug' => 'phu-kien'],
                ['name_vi' => 'Thiết bị âm thanh', 'name_en' => 'Audio Devices', 'slug' => 'thiet-bi-am-thanh'],
            ])->map(fn (array $data) => Category::firstOrCreate(
                ['slug' => $data['slug']],
                $data + ['sort_order' => 0, 'status' => true],
            ))->values();

            $brands = collect([
                ['name_vi' => 'Apple', 'name_en' => 'Apple', 'slug' => 'apple'],
                ['name_vi' => 'Samsung', 'name_en' => 'Samsung', 'slug' => 'samsung'],
                ['name_vi' => 'Xiaomi', 'name_en' => 'Xiaomi', 'slug' => 'xiaomi'],
                ['name_vi' => 'Sony', 'name_en' => 'Sony', 'slug' => 'sony'],
                ['name_vi' => 'Dell', 'name_en' => 'Dell', 'slug' => 'dell'],
                ['name_vi' => 'Asus', 'name_en' => 'Asus', 'slug' => 'asus'],
            ])->map(fn (array $data) => Brand::firstOrCreate(
                ['slug' => $data['slug']],
                $data + ['status' => true],
            ))->values();

            $names = [
                ['Điện thoại thông minh', 'Smartphone'],
                ['Máy tính bảng', 'Tablet'],
                ['Laptop văn phòng', 'Office Laptop'],
                ['Tai nghe không dây', 'Wireless Headphones'],
                ['Loa Bluetooth', 'Bluetooth Speaker'],
                ['Đồng hồ thông minh', 'Smart Watch'],
                ['Bàn phím cơ', 'Mechanical Keyboard'],
                ['Chuột không dây', 'Wireless Mouse'],
                ['Sạc nhanh', 'Fast Charger'],
                ['Màn hình máy tính', 'Computer Monitor'],
            ];

            for ($number = 1; $number <= self::PRODUCT_COUNT; $number++) {
                [$nameVi, $nameEn] = $names[($number - 1) % count($names)];
                $category = $categories[($number - 1) % $categories->count()];
                $brand = $brands[($number - 1) % $brands->count()];
                $sequence = str_pad((string) $number, 3, '0', STR_PAD_LEFT);

                $product = Product::updateOrCreate(
                    ['sku' => 'DEMO-'.$sequence],
                    [
                        'category_id' => $category->id,
                        'brand_id' => $brand->id,
                        'name_vi' => $nameVi.' '.$sequence,
                        'name_en' => $nameEn.' '.$sequence,
                        'slug' => 'demo-san-pham-'.$sequence,
                        'description_vi' => 'Sản phẩm mẫu phục vụ kiểm thử giao diện và chức năng quản trị.',
                        'description_en' => 'Sample product for testing the storefront and administration features.',
                        'price' => 199000 + ($number * 175000),
                        'quantity' => ($number * 7) % 101,
                        'is_featured' => $number % 6 === 0,
                        'status' => $number % 10 !== 0,
                    ],
                );

                $imagePath = $product->id.'/demo'.$sequence.'.png';
                if (! Storage::disk('product_images')->exists($imagePath)) {
                    Storage::disk('product_images')->put($imagePath, $this->placeholderPng($number));
                }

                $existingImage = $product->images()->where('image_url', $imagePath)->first();
                ProductImage::updateOrCreate(
                    ['product_id' => $product->id, 'image_url' => $imagePath],
                    [
                        'alt_text' => $nameVi.' '.$sequence,
                        'sort_order' => 0,
                        'is_primary' => $existingImage?->is_primary
                            ?? ! $product->images()->where('is_primary', true)->exists(),
                    ],
                );
            }
        });
    }

    /**
     * Generate a small valid PNG without requiring GD or an external image service.
     */
    private function placeholderPng(int $number): string
    {
        $palettes = [
            [[66, 114, 215], [163, 189, 245]],
            [[32, 168, 125], [151, 226, 202]],
            [[239, 143, 62], [252, 204, 163]],
            [[137, 83, 186], [207, 174, 237]],
            [[224, 78, 107], [247, 174, 188]],
            [[45, 149, 191], [157, 216, 239]],
        ];
        [$primary, $secondary] = $palettes[($number - 1) % count($palettes)];
        $width = 480;
        $height = 360;
        $raw = '';

        for ($y = 0; $y < $height; $y++) {
            $color = $y < 235 ? $primary : ($y < 285 ? [248, 249, 250] : $secondary);
            $raw .= "\0".str_repeat(pack('CCC', ...$color), $width);
        }

        $signature = "\x89PNG\r\n\x1a\n";
        $header = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

        return $signature
            .$this->pngChunk('IHDR', $header)
            .$this->pngChunk('IDAT', gzcompress($raw, 9))
            .$this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
