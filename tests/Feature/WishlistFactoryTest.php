<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_wishlist_factory_creates_a_valid_customer_product_pair(): void
    {
        $wishlist = Wishlist::factory()->create();

        $this->assertInstanceOf(User::class, $wishlist->user);
        $this->assertInstanceOf(Product::class, $wishlist->product);
        $this->assertTrue($wishlist->product->status);
        $this->assertTrue($wishlist->product->category->status);
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $wishlist->user_id,
            'product_id' => $wishlist->product_id,
        ]);
    }

    public function test_factory_can_attach_multiple_products_to_an_explicit_customer(): void
    {
        $customer = User::factory()->create();

        Wishlist::factory()->count(3)->for($customer)->create();

        $this->assertSame(3, Wishlist::query()->forUser($customer)->count());
        $this->assertSame(3, $customer->wishlistProducts()->count());
    }

    public function test_factory_supports_explicit_product_and_database_cascades(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();

        Wishlist::factory()->for($customer)->for($product)->create();
        $product->delete();

        $this->assertDatabaseCount('wishlists', 0);
    }
}
