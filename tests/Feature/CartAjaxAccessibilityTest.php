<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartAjaxAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_control_exposes_ajax_and_accessible_pending_hooks(): void
    {
        $product = Product::factory()->create(['quantity' => 8]);

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('data-cart-add-form', false)
            ->assertSee('data-product-id="'.$product->id.'"', false)
            ->assertSee('data-pending-text=', false)
            ->assertSee('data-cart-submit', false)
            ->assertSee('data-cart-feedback', false)
            ->assertSee('aria-live="polite"', false);
    }

    public function test_add_ajax_response_returns_server_confirmed_badge_state(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);

        $this->postJson(route('cart.items.store', $product->slug), ['quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('product_id', $product->id)
            ->assertJsonPath('item_quantity', 2)
            ->assertJsonPath('cart_count', 2)
            ->assertJsonStructure(['message', 'line_count', 'subtotal', 'cart_html']);
    }

    public function test_cart_ajax_feedback_and_pending_text_are_localized(): void
    {
        $product = Product::factory()->create();

        $this->withSession(['storefront_locale' => 'vi'])
            ->get(route('products.show', $product->slug))
            ->assertSee('data-pending-text="Đang thêm…"', false)
            ->assertSee('Không thể cập nhật giỏ hàng. Vui lòng thử lại.');

        $this->withSession(['storefront_locale' => 'en'])
            ->get(route('products.show', $product->slug))
            ->assertSee('data-pending-text="Adding…"', false)
            ->assertSee('Your cart could not be updated. Please try again.');
    }

    public function test_frontend_asset_prevents_reload_and_keeps_server_confirmed_guards(): void
    {
        $javascript = file_get_contents(public_path('assets/frontend/js/main.js'));
        $css = file_get_contents(public_path('assets/frontend/css/styles.css'));

        $this->assertStringContainsString("event.target.closest('[data-cart-add-form]')", $javascript);
        $this->assertStringContainsString('event.preventDefault()', $javascript);
        $this->assertStringContainsString("form.dataset.pending === 'true'", $javascript);
        $this->assertStringContainsString("new BroadcastChannel('storefront-cart')", $javascript);
        $this->assertStringContainsString('applyCartState({ ...payload, ...confirmedState }', $javascript);
        $this->assertStringContainsString("Accept: 'application/json'", $javascript);
        $this->assertStringNotContainsString('localStorage', $javascript);
        $this->assertStringContainsString('.cart-add.is-pending', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
    }

    public function test_cart_page_exposes_ajax_mutation_and_keyboard_stepper_hooks(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 8]);
        $cart = Cart::factory()->forCustomer($customer)->create();
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);

        $this->actingAs($customer)
            ->get(route('cart'))
            ->assertOk()
            ->assertSee('data-cart-region', false)
            ->assertSee('data-cart-mutation-form', false)
            ->assertSee('data-cart-action="update"', false)
            ->assertSee('data-cart-action="remove"', false)
            ->assertSee('data-cart-action="clear"', false)
            ->assertSee('data-cart-step="-1"', false)
            ->assertSee('data-cart-step="1"', false)
            ->assertSee('Giảm số lượng', false)
            ->assertSee('Tăng số lượng', false);
    }

    public function test_update_remove_and_clear_json_return_fresh_server_cart_html(): void
    {
        $customer = User::factory()->create();
        $first = Product::factory()->create(['price' => 100000, 'quantity' => 10]);
        $second = Product::factory()->create(['price' => 50000, 'quantity' => 10]);
        $cart = Cart::factory()->forCustomer($customer)->create();
        CartItem::factory()->for($cart)->for($first)->create(['quantity' => 1]);
        CartItem::factory()->for($cart)->for($second)->create(['quantity' => 1]);

        $this->actingAs($customer)
            ->patchJson(route('cart.items.update', $first), ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('cart_count', 4)
            ->assertJsonPath('subtotal', '350000.00')
            ->assertJsonPath('item_quantity', 3)
            ->assertJson(fn ($json) => $json->whereType('cart_html', 'string')->etc());

        $remove = $this->actingAs($customer)->deleteJson(route('cart.items.destroy', $second));
        $remove->assertOk()->assertJsonPath('cart_count', 3);
        $this->assertStringNotContainsString($second->slug, (string) $remove->json('cart_html'));

        $clear = $this->actingAs($customer)->deleteJson(route('cart.clear'));
        $clear->assertOk()->assertJsonPath('cart_count', 0)->assertJsonPath('line_count', 0);
        $this->assertStringContainsString('data-cart-focus', (string) $clear->json('cart_html'));
        $this->assertStringNotContainsString('data-cart-mutation-form', (string) $clear->json('cart_html'));
    }

    public function test_cart_json_refresh_supports_cross_tab_server_reconciliation(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::factory()->forCustomer($customer)->create();
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);

        $this->actingAs($customer)
            ->getJson(route('cart'))
            ->assertOk()
            ->assertJsonPath('cart_count', 2)
            ->assertJsonPath('line_count', 1)
            ->assertJsonStructure(['cart_html', 'subtotal']);
    }

    public function test_frontend_asset_handles_cart_mutation_stale_focus_and_cross_tab_refresh(): void
    {
        $javascript = file_get_contents(public_path('assets/frontend/js/main.js'));

        $this->assertStringContainsString("event.target.closest('[data-cart-mutation-form]')", $javascript);
        $this->assertStringContainsString('requestVersion < cartAppliedVersion', $javascript);
        $this->assertStringContainsString('cartRequestPending', $javascript);
        $this->assertStringContainsString('cartRegion.innerHTML = payload.cart_html', $javascript);
        $this->assertStringContainsString('refreshCartRegion()', $javascript);
        $this->assertStringContainsString("focus({ preventScroll: true })", $javascript);
        $this->assertStringContainsString("event.target.closest('[data-cart-step]')", $javascript);
    }
}
