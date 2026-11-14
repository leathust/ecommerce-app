<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\CartIdentityService;
use App\Support\StorefrontCartContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CartIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Route::middleware('web')->get('/_test/cart-identity/{create?}', function (
            Request $request,
            StorefrontCartContext $context,
            ?string $create = null,
        ) {
            $cart = $context->cart($create === 'create');

            return response()->json([
                'cart_id' => $cart?->id,
                'user_id' => $cart?->user_id,
            ]);
        })->name('test.cart-identity');
    }

    public function test_guest_cart_uses_raw_secure_cookie_and_only_hash_is_persisted(): void
    {
        $response = $this->get('/_test/cart-identity/create')->assertOk();
        $cookie = $response->getCookie(CartIdentityService::COOKIE_NAME);
        $cart = Cart::query()->findOrFail($response->json('cart_id'));

        $this->assertNotNull($cookie);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertFalse($cookie->isSecure());
        $this->assertSame(
            hash('sha256', $cookie->getValue()),
            $cart->session_token,
        );
        $this->assertNotSame($cookie->getValue(), $cart->session_token);

        $this->withCookie(CartIdentityService::COOKIE_NAME, $cookie->getValue())
            ->get('/_test/cart-identity/create')
            ->assertOk()
            ->assertJsonPath('cart_id', $cart->id);

        $this->assertDatabaseCount('carts', 1);

    }

    public function test_guest_identity_cookie_is_secure_on_https(): void
    {
        $response = $this->get('https://localhost/_test/cart-identity/create')->assertOk();

        $this->assertTrue($response->getCookie(CartIdentityService::COOKIE_NAME)->isSecure());
    }

    public function test_resolver_does_not_create_cart_on_read_and_isolates_customer_and_admin(): void
    {
        $this->get('/_test/cart-identity')
            ->assertOk()
            ->assertJsonPath('cart_id', null);
        $this->assertDatabaseCount('carts', 0);

        $customer = $this->customer();
        $this->actingAs($customer)
            ->get('/_test/cart-identity/create')
            ->assertOk()
            ->assertJsonPath('user_id', $customer->id);
        $this->assertDatabaseHas('carts', ['user_id' => $customer->id, 'session_token' => null]);

        $admin = $this->admin();
        $this->actingAs($admin)
            ->get('/_test/cart-identity/create')
            ->assertOk()
            ->assertJsonPath('cart_id', null);
        $this->assertDatabaseCount('carts', 1);
    }

    public function test_login_merges_guest_cart_into_customer_with_stock_cap_and_revokes_token(): void
    {
        $customer = $this->customer();
        $limited = Product::factory()->create(['quantity' => 5]);
        $unavailable = Product::factory()->outOfStock()->create();
        $customerCart = Cart::factory()->forCustomer($customer)->create();
        CartItem::factory()->for($customerCart)->for($limited)->create(['quantity' => 4]);

        $rawToken = str_repeat('g', 43);
        $guestCart = Cart::factory()->create([
            'session_token' => hash('sha256', $rawToken),
        ]);
        CartItem::factory()->for($guestCart)->for($limited)->create(['quantity' => 3]);
        CartItem::factory()->for($guestCart)->for($unavailable)->create(['quantity' => 2]);

        $response = $this->withCookie(CartIdentityService::COOKIE_NAME, $rawToken)
            ->post(route('login.submit'), [
                'email' => $customer->email,
                'password' => 'secret-password',
            ]);

        $response->assertRedirect(route('home'))
            ->assertCookieExpired(CartIdentityService::COOKIE_NAME);
        $this->assertAuthenticatedAs($customer);
        $this->assertDatabaseMissing('carts', ['id' => $guestCart->id]);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $customerCart->id,
            'product_id' => $limited->id,
            'quantity' => 5,
        ]);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $customerCart->id,
            'product_id' => $unavailable->id,
            'quantity' => 2,
        ]);
    }

    public function test_login_moves_guest_cart_when_customer_has_no_cart_and_stale_token_cannot_resolve(): void
    {
        $customer = $this->customer();
        $product = Product::factory()->create(['quantity' => 10]);
        $rawToken = str_repeat('m', 43);
        $guestCart = Cart::factory()->create(['session_token' => hash('sha256', $rawToken)]);
        CartItem::factory()->for($guestCart)->for($product)->create(['quantity' => 3]);

        $this->withCookie(CartIdentityService::COOKIE_NAME, $rawToken)
            ->post(route('login.submit'), [
                'email' => $customer->email,
                'password' => 'secret-password',
            ])->assertRedirect(route('home'));

        $customerCart = Cart::query()->forUser($customer)->firstOrFail();
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $customerCart->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        auth()->logout();
        $this->withCookie(CartIdentityService::COOKIE_NAME, $rawToken)
            ->get('/_test/cart-identity')
            ->assertOk()
            ->assertJsonPath('cart_id', null);
    }

    public function test_logout_keeps_customer_cart_but_expires_any_guest_identity_cookie(): void
    {
        $customer = $this->customer();
        $cart = Cart::factory()->forCustomer($customer)->create();

        $this->actingAs($customer)
            ->withCookie(CartIdentityService::COOKIE_NAME, str_repeat('x', 43))
            ->post(route('logout'))
            ->assertRedirect(route('home'))
            ->assertCookieExpired(CartIdentityService::COOKIE_NAME);

        $this->assertGuest();
        $this->assertDatabaseHas('carts', ['id' => $cart->id, 'user_id' => $customer->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'cart-customer@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ], $attributes));
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->attach(Role::query()->where('name', 'admin')->firstOrFail());

        return $admin;
    }
}
