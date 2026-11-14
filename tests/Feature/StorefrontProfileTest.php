<?php

namespace Tests\Feature;

use App\Http\Controllers\StorefrontProfileController;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StorefrontProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_account_route_keeps_its_canonical_name_and_uses_the_dedicated_controller(): void
    {
        $route = Route::getRoutes()->getByName('account');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('account', $route->uri());
        $this->assertSame(StorefrontProfileController::class.'@show', $route->getActionName());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('customer', $route->gatherMiddleware());
        $this->assertContains('storefront.locale', $route->gatherMiddleware());
    }

    public function test_profile_mutation_routes_are_named_throttled_and_do_not_collide_with_admin_template(): void
    {
        $profile = Route::getRoutes()->getByName('storefront.profile.update');
        $password = Route::getRoutes()->getByName('storefront.password.update');
        $adminTemplate = Route::getRoutes()->getByName('account.profile');

        $this->assertSame(['PATCH'], $profile->methods());
        $this->assertSame('account/profile', $profile->uri());
        $this->assertSame(StorefrontProfileController::class.'@updateProfile', $profile->getActionName());
        $this->assertContains('auth', $profile->gatherMiddleware());
        $this->assertContains('customer', $profile->gatherMiddleware());
        $this->assertContains('throttle:profile-mutations', $profile->gatherMiddleware());

        $this->assertSame(['PUT'], $password->methods());
        $this->assertSame('account/password', $password->uri());
        $this->assertContains('throttle:profile-mutations', $password->gatherMiddleware());

        $this->assertNotNull($adminTemplate);
        $this->assertSame('account/profile', $adminTemplate->uri());
        $this->assertNotSame($adminTemplate->getName(), $profile->getName());
    }

    public function test_guest_admin_and_inactive_customer_cannot_view_customer_profile(): void
    {
        $this->get(route('account'))->assertRedirect(route('login'));

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->firstOrFail());
        $this->actingAs($admin)->get(route('account'))->assertForbidden();

        auth()->logout();

        $inactive = User::factory()->create(['status' => 'inactive']);
        $this->actingAs($inactive)
            ->get(route('account'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_active_customer_receives_only_the_allowlisted_account_view_data_and_shortcuts(): void
    {
        $customer = User::factory()->create([
            'name' => 'Fallback Name',
            'first_name' => 'An',
            'last_name' => 'Nguyen',
            'phone' => '0900000000',
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($customer)->get(route('account'));

        $response->assertOk()
            ->assertViewIs('frontend.account')
            ->assertViewHas('account', function (array $account) use ($customer): bool {
                $this->assertSame([
                    'display_name',
                    'first_name',
                    'last_name',
                    'email',
                    'username',
                    'phone',
                    'status',
                    'registered_at',
                ], array_keys($account));
                $this->assertSame('An Nguyen', $account['display_name']);
                $this->assertSame($customer->email, $account['email']);
                $this->assertArrayNotHasKey('password', $account);
                $this->assertArrayNotHasKey('roles', $account);
                $this->assertArrayNotHasKey('permissions', $account);
                $this->assertArrayNotHasKey('must_change_password', $account);

                return true;
            })
            ->assertViewHas('accountLinks', [
                'cart' => route('cart'),
                'wishlist' => route('wishlist.index'),
            ])
            ->assertSee(route('cart'), false)
            ->assertSee(route('wishlist.index'), false)
            ->assertDontSee('must_change_password');
    }

    public function test_profile_page_keeps_shared_layout_queries_bounded_and_never_queries_permissions(): void
    {
        $customer = User::factory()->create();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $this->actingAs($customer)->get(route('account'))->assertOk();

        $joined = implode('\n', $queries);
        $this->assertStringNotContainsString('permissions', $joined);
        $roleQueries = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'roles'));
        $this->assertLessThanOrEqual(3, count($roleQueries));
        $this->assertLessThanOrEqual(10, count($queries));
    }

    public function test_profile_and_password_mutations_are_active_with_separate_validation_contracts(): void
    {
        $customer = User::factory()->create([
            'first_name' => 'Original',
            'phone' => '0900000000',
        ]);

        $this->actingAs($customer)
            ->patch(route('storefront.profile.update'), [
                'first_name' => 'Changed',
                'status' => 'blocked',
            ])
            ->assertRedirect(route('account'));

        $customer->refresh();
        $this->assertSame('Changed', $customer->first_name);
        $this->assertNull($customer->phone);
        $this->assertSame('active', $customer->status);

        $this->actingAs($customer)
            ->put(route('storefront.password.update'), [])
            ->assertSessionHasErrors(['current_password', 'password'], null, 'password');
    }
}
