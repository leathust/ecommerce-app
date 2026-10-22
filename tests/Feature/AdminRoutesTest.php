<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_can_view_admin_login_and_is_redirected_from_dashboard(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee(route('admin.login.submit'), false)
            ->assertSee('name="_token"', false)
            ->assertSee('assets/admin/js/vanilla-utils.js', false)
            ->assertDontSee('onsubmit="return false"', false);

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_log_in_through_the_official_route(): void
    {
        $user = $this->userWithRole('admin', [
            'email' => 'route-admin@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->post(route('admin.login.submit'), [
            'email' => $user->email,
            'password' => 'secret-password',
            'remember' => '1',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_admin_is_redirected_away_from_login(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_dashboard_routes_are_protected_and_accessible(): void
    {
        $user = $this->userWithRole('order_manager');

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_business_routes_enforce_their_specific_permission(): void
    {
        $orderManager = $this->userWithRole('order_manager');

        $this->actingAs($orderManager)
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('Đơn hàng')
            ->assertDontSee(route('admin.products'), false);

        $this->actingAs($orderManager)
            ->get(route('admin.products'))
            ->assertForbidden();

        $catalogManager = $this->userWithRole('catalog_manager');

        $this->actingAs($catalogManager)
            ->get(route('admin.products'))
            ->assertOk()
            ->assertSee('Sản phẩm')
            ->assertDontSee(route('admin.orders'), false);

        $this->assertContains(
            'permission:orders.view',
            Route::getRoutes()->getByName('admin.orders')->gatherMiddleware(),
        );
        $this->assertContains(
            'permission:products.view',
            Route::getRoutes()->getByName('admin.products')->gatherMiddleware(),
        );
        $this->assertContains(
            'permission:customers.view',
            Route::getRoutes()->getByName('admin.customers')->gatherMiddleware(),
        );
    }

    public function test_admin_template_routes_are_not_public(): void
    {
        $this->get(route('account.profile'))->assertRedirect(route('home'));
        $this->get(route('elements.buttons'))->assertRedirect(route('home'));
    }

    public function test_blocked_admin_is_logged_out_before_dashboard_access(): void
    {
        $user = $this->userWithRole(Role::SUPER_ADMIN, ['status' => 'blocked']);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(url('/admin'));

        $this->assertGuest();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }
}
