<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CartIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CartStorefrontIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_uses_real_guest_cart_count_and_hides_zero_badge(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('cart'), false)
            ->assertDontSee('data-cart-count', false);
        $this->assertDatabaseCount('carts', 0);

        $rawToken = str_repeat('h', 43);
        $product = Product::factory()->create(['quantity' => 3]);
        $cart = Cart::factory()->create(['session_token' => hash('sha256', $rawToken)]);
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 5]);

        $this->withCookie(CartIdentityService::COOKIE_NAME, $rawToken)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-cart-count', false)
            ->assertSee('>3</span>', false)
            ->assertDontSee('>2</span>', false);
    }

    public function test_customer_header_count_excludes_unavailable_lines(): void
    {
        $customer = User::factory()->create();
        $available = Product::factory()->create(['quantity' => 10]);
        $unavailable = Product::factory()->outOfStock()->create();
        $cart = Cart::factory()->forCustomer($customer)->create();
        CartItem::factory()->for($cart)->for($available)->create(['quantity' => 4]);
        CartItem::factory()->for($cart)->for($unavailable)->create(['quantity' => 8]);

        $this->actingAs($customer)
            ->get(route('shop'))
            ->assertOk()
            ->assertSee('data-cart-count', false)
            ->assertSee('>4</span>', false);
    }

    public function test_home_shop_trending_and_related_cards_share_real_add_form(): void
    {
        $product = Product::factory()->create(['is_featured' => true, 'quantity' => 12]);
        $related = Product::factory()->create([
            'category_id' => $product->category_id,
            'quantity' => 8,
        ]);
        $action = route('cart.items.store', ['storefrontProduct' => $product->slug]);
        $relatedAction = route('cart.items.store', ['storefrontProduct' => $related->slug]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($action, false)
            ->assertSee('data-cart-add-form', false);

        $this->get(route('shop'))
            ->assertOk()
            ->assertSee($action, false)
            ->assertSee($relatedAction, false);

        $trendingResponse = $this->withHeader('referer', route('home'))
            ->getJson(route('home.trending', ['category' => $product->category->slug]))
            ->assertOk();

        $this->assertStringContainsString($action, (string) $trendingResponse->json('html'));

        $this->get(route('products.show', ['storefrontProduct' => $product->slug]))
            ->assertOk()
            ->assertSee($relatedAction, false);
    }

    public function test_product_detail_has_quantity_form_and_replaces_pending_placeholder(): void
    {
        $product = Product::factory()->create(['quantity' => 7]);

        $this->get(route('products.show', ['storefrontProduct' => $product->slug]))
            ->assertOk()
            ->assertSee(route('cart.items.store', ['storefrontProduct' => $product->slug]), false)
            ->assertSee('name="quantity"', false)
            ->assertSee('max="7"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="return_to"', false)
            ->assertDontSee('cart-pending', false)
            ->assertDontSee(__('frontend.catalog.cart_pending'));
    }

    public function test_out_of_stock_cards_and_detail_render_disabled_control_without_post_form(): void
    {
        $product = Product::factory()->outOfStock()->create();
        $action = route('cart.items.store', ['storefrontProduct' => $product->slug]);

        $this->get(route('shop'))
            ->assertOk()
            ->assertSee('cart-add--disabled', false)
            ->assertDontSee($action, false);

        $this->get(route('products.show', ['storefrontProduct' => $product->slug]))
            ->assertOk()
            ->assertSee('cart-add--disabled', false)
            ->assertSee('disabled', false)
            ->assertDontSee($action, false);
    }

    public function test_shared_card_add_form_keeps_no_javascript_contract(): void
    {
        $product = Product::factory()->create();

        $this->get(route('shop'))
            ->assertOk()
            ->assertSee('method="POST"', false)
            ->assertSee('action="'.route('cart.items.store', ['storefrontProduct' => $product->slug]).'"', false)
            ->assertSee('name="quantity" value="1"', false)
            ->assertSee('name="return_to"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_cart_context_query_count_is_bounded_across_many_cards(): void
    {
        $customer = User::factory()->create();
        $cartProduct = Product::factory()->create(['quantity' => 20]);
        $cart = Cart::factory()->forCustomer($customer)->create();
        CartItem::factory()->for($cart)->for($cartProduct)->create(['quantity' => 3]);
        Product::factory()->count(18)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($customer)->get(route('shop'))->assertOk();
        $cartQueries = collect(DB::getQueryLog())->filter(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'carts') || str_contains($sql, 'cart_items');
        });
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            2,
            $cartQueries->count(),
            $cartQueries->pluck('query')->implode("\n"),
        );
    }
}
