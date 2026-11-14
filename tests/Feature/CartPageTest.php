<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartIdentityService;
use App\Services\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CartPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_cart_page_is_localized_and_does_not_create_guest_cart(): void
    {
        $this->withSession(['storefront_locale' => 'vi'])
            ->get(route('cart'))
            ->assertOk()
            ->assertViewIs('frontend.cart')
            ->assertSee('Giỏ hàng đang trống')
            ->assertSee(route('shop'), false)
            ->assertDontSee('WELCOME20')
            ->assertDontSee('Estimated tax')
            ->assertDontSee('Apple HomePod');

        $this->assertDatabaseCount('carts', 0);
    }

    public function test_customer_cart_renders_real_products_prices_totals_and_no_js_forms(): void
    {
        $customer = User::factory()->create();
        $first = Product::factory()->create([
            'name_vi' => 'Điện thoại kiểm thử',
            'price' => 125000,
            'quantity' => 10,
        ]);
        $second = Product::factory()->create([
            'name_vi' => 'Tai nghe kiểm thử',
            'price' => 50000,
            'quantity' => 10,
        ]);
        $cart = Cart::factory()->forCustomer($customer)->create();
        CartItem::factory()->for($cart)->for($first)->create(['quantity' => 2]);
        CartItem::factory()->for($cart)->for($second)->create(['quantity' => 1]);

        $this->actingAs($customer)
            ->get(route('cart'))
            ->assertOk()
            ->assertSee('Điện thoại kiểm thử')
            ->assertSee('Tai nghe kiểm thử')
            ->assertSee('250.000 ₫')
            ->assertSee('300.000 ₫')
            ->assertSee(route('cart.items.update', $first), false)
            ->assertSee(route('cart.items.destroy', $first), false)
            ->assertSee(route('cart.clear'), false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_guest_cookie_cart_is_resolved_without_exposing_token(): void
    {
        $rawToken = str_repeat('p', 43);
        $product = Product::factory()->create(['name_vi' => 'Sản phẩm guest']);
        $cart = Cart::factory()->create(['session_token' => hash('sha256', $rawToken)]);
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);

        $this->withCookie(CartIdentityService::COOKIE_NAME, $rawToken)
            ->get(route('cart'))
            ->assertOk()
            ->assertSee('Sản phẩm guest')
            ->assertDontSee($rawToken)
            ->assertDontSee($cart->session_token);
    }

    public function test_cart_localizes_product_fields_and_uses_managed_primary_image(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create([
            'name_vi' => 'Tên tiếng Việt',
            'name_en' => 'English cart product',
            'price' => 99000,
        ]);
        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'image_url' => 'products/cart-test.jpg',
            'alt_text' => 'Cart product image',
            'is_primary' => true,
        ]);
        $cart = Cart::factory()->forCustomer($customer)->create();
        CartItem::factory()->for($cart)->for($product)->create();

        $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'en'])
            ->get(route('cart'))
            ->assertOk()
            ->assertSee('Your cart')
            ->assertSee('English cart product')
            ->assertDontSee('Tên tiếng Việt')
            ->assertSee(route('product-images.show', $image), false)
            ->assertSee('Cart product image');
    }

    public function test_unavailable_lines_remain_removable_but_are_excluded_from_totals(): void
    {
        $customer = User::factory()->create();
        $available = Product::factory()->create(['price' => 100000, 'quantity' => 5]);
        $unavailable = Product::factory()->outOfStock()->create([
            'name_vi' => 'Sản phẩm đã hết hàng',
            'price' => 900000,
        ]);
        $cart = Cart::factory()->forCustomer($customer)->create();
        CartItem::factory()->for($cart)->for($available)->create(['quantity' => 2]);
        CartItem::factory()->for($cart)->for($unavailable)->create(['quantity' => 3]);

        $response = $this->actingAs($customer)->get(route('cart'));

        $response->assertOk()
            ->assertSee('Sản phẩm đã hết hàng')
            ->assertSee('Sản phẩm hiện đã hết hàng')
            ->assertSee('Không tính vào tổng')
            ->assertSee('200.000 ₫')
            ->assertSee(route('cart.items.destroy', $unavailable), false)
            ->assertDontSee('id="cart-quantity-'.$unavailable->id.'"', false);
    }

    public function test_shipping_and_minimum_order_settings_are_presented_as_estimates(): void
    {
        $this->settings([
            'commerce.default_shipping_fee' => '30000.00',
            'commerce.free_shipping_threshold' => '500000.00',
            'commerce.minimum_order_amount' => '400000.00',
        ]);
        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 100000, 'quantity' => 10]);
        $cart = Cart::factory()->forCustomer($customer)->create();
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);

        $this->actingAs($customer)
            ->get(route('cart'))
            ->assertOk()
            ->assertSee('200.000 ₫')
            ->assertSee('30.000 ₫')
            ->assertSee('230.000 ₫')
            ->assertSee('Mua thêm 300.000 ₫')
            ->assertSee('Cần thêm 200.000 ₫')
            ->assertSee(route('checkout'), false)
            ->assertSee('Tiến hành đặt hàng');

        CartItem::query()->where('cart_id', $cart->id)->update(['quantity' => 5]);
        $this->actingAs($customer)
            ->get(route('cart'))
            ->assertOk()
            ->assertSee('Miễn phí')
            ->assertSee('500.000 ₫');
    }

    public function test_cart_page_query_count_does_not_grow_per_item(): void
    {
        $customer = User::factory()->create();
        $cart = Cart::factory()->forCustomer($customer)->create();
        CartItem::factory()->count(12)->for($cart)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($customer)->get(route('cart'))->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(12, $queryCount);
    }

    public function test_cart_page_escapes_catalog_content(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create([
            'name_vi' => '<script>alert("cart")</script>',
            'name_en' => '<script>alert("cart")</script>',
        ]);
        $cart = Cart::factory()->forCustomer($customer)->create();
        CartItem::factory()->for($cart)->for($product)->create();

        $this->actingAs($customer)
            ->get(route('cart'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;cart&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("cart")</script>', false);
    }

    /** @param array<string, string> $values */
    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value],
            );
        }

        app(SystemSettingService::class)->forgetCache();
    }
}
