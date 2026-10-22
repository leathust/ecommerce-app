<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAuthorizationMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Route::middleware(['web', 'active'])->get('/test/active', fn () => 'allowed');
        Route::middleware(['web', 'admin'])->get('/test/admin', fn () => 'allowed');
        Route::middleware(['web', 'permission:orders.update'])->get('/test/orders', fn () => 'allowed');
        Route::middleware(['web', 'permission:products.delete|orders.view'])->get('/test/any-permission', fn () => 'allowed');
        Route::middleware(['web', 'permission:future.permission'])->get('/test/super-admin', fn () => 'allowed');
    }

    public function test_inactive_user_is_logged_out_and_redirected(): void
    {
        $user = User::factory()->create(['status' => 'blocked']);

        $response = $this->actingAs($user)->get('/test/active');

        $response->assertRedirect(url('/admin'));
        $response->assertSessionHasErrors('auth');
        $this->assertTrue(Auth::guest());
    }

    public function test_active_user_passes_status_middleware(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get('/test/active')->assertOk();
    }

    public function test_regular_user_is_forbidden_from_admin_area(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/test/admin')->assertForbidden();
    }

    public function test_administrative_role_passes_admin_middleware(): void
    {
        $user = $this->userWithRole('order_manager');

        $this->actingAs($user)->get('/test/admin')->assertOk();
    }

    public function test_permission_middleware_allows_and_denies_by_role_permissions(): void
    {
        $user = $this->userWithRole('order_manager');

        $this->actingAs($user)->get('/test/orders')->assertOk();
        $this->actingAs($user)->get('/test/any-permission')->assertOk();

        $catalogUser = $this->userWithRole('catalog_manager');
        $this->actingAs($catalogUser)->get('/test/orders')->assertForbidden();
    }

    public function test_super_admin_bypasses_permission_middleware(): void
    {
        $user = $this->userWithRole(Role::SUPER_ADMIN);

        $this->actingAs($user)->get('/test/super-admin')->assertOk();
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }
}
