<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserAuthorizationModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_role_and_status_helpers(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::where('name', 'catalog_manager')->firstOrFail();
        $user->roles()->attach($role);

        $this->assertTrue($user->isActive());
        $this->assertTrue($user->hasRole('catalog_manager'));
        $this->assertTrue($user->hasAnyRole(['admin', 'catalog_manager']));
        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->hasRole('order_manager'));
    }

    public function test_user_receives_permissions_through_roles(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'order_manager')->firstOrFail());

        $this->assertTrue($user->hasPermission('orders.update'));
        $this->assertTrue($user->hasAnyPermission(['products.delete', 'orders.view']));
        $this->assertFalse($user->hasPermission('products.delete'));
    }

    public function test_super_admin_is_allowed_even_for_a_new_permission(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', Role::SUPER_ADMIN)->firstOrFail());

        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->hasPermission('future.permission'));
    }

    public function test_authorization_query_scopes(): void
    {
        $catalogUser = User::factory()->create();
        $catalogUser->roles()->attach(Role::where('name', 'catalog_manager')->firstOrFail());

        $orderUser = User::factory()->create();
        $orderUser->roles()->attach(Role::where('name', 'order_manager')->firstOrFail());

        $this->assertTrue(User::role('catalog_manager')->whereKey($catalogUser)->exists());
        $this->assertFalse(User::role('catalog_manager')->whereKey($orderUser)->exists());
        $this->assertTrue(User::permission('orders.update')->whereKey($orderUser)->exists());
        $this->assertTrue(Role::withPermission('products.update')->where('name', 'catalog_manager')->exists());
        $this->assertTrue(Permission::assignedToRole('order_manager')->where('name', 'orders.view')->exists());
    }

    public function test_laravel_gate_uses_database_permissions(): void
    {
        $orderManager = User::factory()->create();
        $orderManager->roles()->attach(Role::where('name', 'order_manager')->firstOrFail());

        $this->assertTrue(Gate::forUser($orderManager)->allows('orders.view'));
        $this->assertFalse(Gate::forUser($orderManager)->allows('products.view'));

        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach(Role::where('name', Role::SUPER_ADMIN)->firstOrFail());

        $this->assertTrue(Gate::forUser($superAdmin)->allows('future.permission'));
    }
}
