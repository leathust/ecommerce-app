<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistAjaxAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_toggle_exposes_ajax_endpoints_and_accessible_server_state(): void
    {
        $customer = User::factory()->create();
        $product = $this->product('ajax-hooks');

        $this->actingAs($customer)
            ->get(route('shop'))
            ->assertOk()
            ->assertSee('data-wishlist-form', false)
            ->assertSee('data-product-id="'.$product->id.'"', false)
            ->assertSee('data-store-url="'.route('wishlist.store', $product->slug).'"', false)
            ->assertSee('data-destroy-url="'.route('wishlist.destroy', $product).'"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('data-wishlist-feedback', false)
            ->assertSee('aria-live="polite"', false);

    }

    public function test_wishlist_controls_and_feedback_are_localized_in_vietnamese_and_english(): void
    {
        $customer = User::factory()->create();
        $product = $this->product('localized-hooks');

        $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'vi'])
            ->get(route('products.show', $product->slug))
            ->assertSee('data-add-text="Thêm vào yêu thích"', false)
            ->assertSee('Không thể cập nhật danh sách yêu thích. Vui lòng thử lại.');

        $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'en'])
            ->get(route('products.show', $product->slug))
            ->assertSee('data-add-text="Add to wishlist"', false)
            ->assertSee('Your wishlist could not be updated. Please try again.');
    }

    public function test_wishlist_page_keeps_native_remove_form_without_duplicate_ajax_toggle(): void
    {
        $customer = User::factory()->create();
        $product = $this->product('native-remove');
        Wishlist::query()->create(['user_id' => $customer->id, 'product_id' => $product->id]);

        $this->actingAs($customer)
            ->get(route('wishlist.index'))
            ->assertOk()
            ->assertDontSee('data-wishlist-form', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee(route('wishlist.destroy', $product), false);
    }

    public function test_frontend_assets_include_pending_error_cross_tab_and_reduced_motion_guards(): void
    {
        $javascript = file_get_contents(public_path('assets/frontend/js/main.js'));
        $css = file_get_contents(public_path('assets/frontend/css/styles.css'));

        $this->assertStringContainsString("event.target.closest('[data-wishlist-form]')", $javascript);
        $this->assertStringContainsString("form.dataset.pending === 'true'", $javascript);
        $this->assertStringContainsString("new BroadcastChannel('storefront-wishlist')", $javascript);
        $this->assertStringContainsString("Accept: 'application/json'", $javascript);
        $this->assertStringNotContainsString('localStorage', $javascript);
        $this->assertStringContainsString('.wishlist-feedback.is-error', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
    }

    private function product(string $slug): Product
    {
        $category = Category::query()->create([
            'name_vi' => 'Danh mục '.$slug,
            'name_en' => 'Category '.$slug,
            'slug' => 'category-'.$slug,
            'status' => true,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SKU-'.strtoupper($slug),
            'name_vi' => 'Sản phẩm '.$slug,
            'name_en' => 'Product '.$slug,
            'slug' => $slug,
            'price' => 100000,
            'quantity' => 10,
            'status' => true,
        ]);
    }
}
