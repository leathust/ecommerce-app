<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StorefrontAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        RateLimiter::clear('storefront-login|customer@example.com|127.0.0.1');
    }

    public function test_guest_can_view_localized_customer_login_form(): void
    {
        $this->withSession(['storefront_locale' => 'vi'])
            ->get(route('login', ['return_to' => '/shop?page=2']))
            ->assertOk()
            ->assertViewIs('frontend.auth.login')
            ->assertSee('Đăng nhập')
            ->assertSee('name="_token"', false)
            ->assertSee('name="return_to" value="/shop?page=2"', false)
            ->assertSee('noindex,nofollow', false);

        $this->withSession(['storefront_locale' => 'en'])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('Sign in');
    }

    public function test_active_customer_can_login_with_safe_return_url_and_session_is_regenerated(): void
    {
        $customer = $this->customer();
        $this->withSession(['sentinel' => 'present']);
        $before = session()->getId();

        $this->post(route('login.submit'), [
            'email' => $customer->email,
            'password' => 'secret-password',
            'remember' => '1',
            'return_to' => '/shop?brand=acme',
        ])->assertRedirect('/shop?brand=acme');

        $this->assertAuthenticatedAs($customer);
        $this->assertNotSame($before, session()->getId());
        $this->assertSame('present', session('sentinel'));
    }

    public function test_intended_url_is_used_only_when_it_is_safe(): void
    {
        $customer = $this->customer();

        $this->withSession(['url.intended' => url('/wishlist')])
            ->post(route('login.submit'), [
                'email' => $customer->email,
                'password' => 'secret-password',
            ])->assertRedirect(url('/wishlist'));

        auth()->logout();

        $this->withSession(['url.intended' => url('/admin/users')])
            ->post(route('login.submit'), [
                'email' => $customer->email,
                'password' => 'secret-password',
            ])->assertRedirect(route('home'));
    }

    public function test_external_protocol_relative_and_admin_return_urls_are_rejected(): void
    {
        foreach (['https://evil.example/steal', '//evil.example/steal', '/admin/users', 'javascript:alert(1)'] as $returnTo) {
            $customer = $this->customer(['email' => fake()->unique()->safeEmail()]);

            $this->post(route('login.submit'), [
                'email' => $customer->email,
                'password' => 'secret-password',
                'return_to' => $returnTo,
            ])->assertRedirect(route('home'));

            auth()->logout();
        }
    }

    public function test_invalid_blocked_inactive_and_admin_accounts_are_rejected_generically(): void
    {
        $customer = $this->customer();
        $blocked = $this->customer(['email' => 'blocked@example.com', 'status' => 'blocked']);
        $inactive = $this->customer(['email' => 'inactive@example.com', 'status' => 'inactive']);
        $admin = $this->admin();

        foreach ([
            [$customer->email, 'wrong-password'],
            [$blocked->email, 'secret-password'],
            [$inactive->email, 'secret-password'],
            [$admin->email, 'secret-password'],
        ] as [$email, $password]) {
            $this->post(route('login.submit'), compact('email', 'password'))
                ->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_repeated_failures_are_rate_limited(): void
    {
        $customer = $this->customer();

        for ($attempt = 0; $attempt < config('auth.storefront_login.max_attempts'); $attempt++) {
            $this->post(route('login.submit'), [
                'email' => $customer->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('login.submit'), [
            'email' => $customer->email,
            'password' => 'secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_customer_can_logout_and_admin_cannot_use_customer_logout(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));

        $customer = $this->customer();

        $this->actingAs($customer)
            ->post(route('logout'))
            ->assertRedirect(route('home'));
        $this->assertGuest();

        $admin = $this->admin();
        $this->actingAs($admin)
            ->post(route('logout'))
            ->assertForbidden();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_inactive_customer_is_logged_out_by_customer_middleware_for_html_and_json(): void
    {
        $inactive = $this->customer(['status' => 'inactive']);

        $this->actingAs($inactive)
            ->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $blocked = $this->customer(['email' => 'json-blocked@example.com', 'status' => 'blocked']);
        $this->actingAs($blocked)
            ->postJson(route('logout'))
            ->assertForbidden()
            ->assertJsonStructure(['message']);
        $this->assertGuest();
    }

    public function test_login_and_logout_routes_keep_web_locale_auth_customer_and_csrf_boundaries(): void
    {
        $login = Route::getRoutes()->getByName('login');
        $submit = Route::getRoutes()->getByName('login.submit');
        $logout = Route::getRoutes()->getByName('logout');

        $this->assertSame(['GET', 'HEAD'], $login->methods());
        $this->assertSame(['POST'], $submit->methods());
        $this->assertSame(['POST'], $logout->methods());
        $this->assertContains('web', $submit->gatherMiddleware());
        $this->assertContains('storefront.locale', $submit->gatherMiddleware());
        $this->assertContains('auth', $logout->gatherMiddleware());
        $this->assertContains('customer', $logout->gatherMiddleware());
    }

    public function test_authenticated_users_are_redirected_to_their_own_portal(): void
    {
        $this->actingAs($this->customer())
            ->get(route('login'))
            ->assertRedirect(route('home'));

        auth()->logout();

        $this->actingAs($this->admin())
            ->get(route('login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_account_page_requires_an_active_customer_and_exposes_real_logout_form(): void
    {
        $this->get(route('account'))->assertRedirect(route('login'));

        $customer = $this->customer();
        $this->actingAs($customer)
            ->get(route('account'))
            ->assertOk()
            ->assertSee($customer->email)
            ->assertSee(route('logout'), false)
            ->assertSee('name="_token"', false);

        auth()->logout();

        $this->actingAs($this->admin())
            ->get(route('account'))
            ->assertForbidden();
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'customer@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ], $attributes));
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'storefront-admin@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ]);
        $admin->roles()->attach(Role::where('name', 'admin')->firstOrFail());

        return $admin;
    }
}
