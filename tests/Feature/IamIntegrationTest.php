<?php

namespace Tests\Feature;

use App\Exceptions\IamOperationException;
use App\Models\AdminAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\IamService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IamIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private IamService $iam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->iam = app(IamService::class);
    }

    public function test_custom_admin_combines_multiple_roles_and_permission_revocation_is_immediate(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $catalogRole = $this->customRole('custom_catalog', ['products.view']);
        $orderRole = $this->customRole('custom_orders', ['orders.view']);
        $operator = User::factory()->create(['status' => 'active']);
        $operator->roles()->attach([$catalogRole->id, $orderRole->id]);

        $this->assertTrue($operator->isAdmin());
        $this->assertFalse($operator->isCustomer());
        $this->assertEqualsCanonicalizing(
            ['orders.view', 'products.view'],
            $operator->effectivePermissionNames()->all(),
        );

        $this->actingAs($operator)->get(route('admin.products'))->assertOk();
        $this->actingAs($operator)->get(route('admin.orders'))->assertOk();
        $this->actingAs($operator)->get(route('admin.users.index'))->assertForbidden();

        $this->iam->syncRolePermissions($superAdmin, $catalogRole, []);

        $this->actingAs($operator)->get(route('admin.products'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.orders'))->assertOk();
    }

    public function test_removing_last_admin_role_is_enforced_by_middleware_on_next_request(): void
    {
        $role = $this->customRole('temporary_admin', ['dashboard.view']);
        $operator = User::factory()->create(['status' => 'active']);
        $operator->roles()->attach($role);

        $this->actingAs($operator)->get(route('admin.dashboard'))->assertOk();

        $operator->roles()->detach($role);

        $this->actingAs($operator)->get(route('admin.dashboard'))->assertForbidden();
        $this->assertTrue($operator->fresh()->isCustomer());
    }

    public function test_minimum_role_matrix_controls_user_crud_and_super_admin_management(): void
    {
        $admin = $this->userWithRole('admin');
        $catalogManager = $this->userWithRole('catalog_manager');
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $target = $this->userWithRole('order_manager');

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.edit', $target))->assertOk();
        $this->actingAs($catalogManager)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.roles.create'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.access-logs.index'))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('admin.access-logs.index'))->assertOk();

        $this->expectIamException(fn () => $this->iam->syncUserRoles(
            $admin,
            $superAdmin,
            [Role::where('name', 'admin')->firstOrFail()->id],
        ));
        $this->assertTrue($superAdmin->fresh()->isSuperAdmin());
    }

    public function test_self_actions_last_super_admin_and_system_role_identity_remain_protected(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $protectedRole = Role::where('name', Role::SUPER_ADMIN)->firstOrFail();

        $this->expectIamException(fn () => $this->iam->updateUserStatus($superAdmin, $superAdmin, 'blocked'));
        $this->expectIamException(fn () => $this->iam->deleteAdminUser($superAdmin, $superAdmin));
        $this->expectIamException(fn () => $this->iam->syncUserRoles($superAdmin, $superAdmin, [$adminRole->id]));

        $this->actingAs($superAdmin)
            ->put(route('admin.roles.update', $adminRole), [
                'name' => 'renamed_system_role',
                'permission_ids' => [],
            ])
            ->assertSessionHasErrors('name');
        $this->actingAs($superAdmin)
            ->get(route('admin.roles.edit', $protectedRole))
            ->assertForbidden();
        $this->actingAs($superAdmin)
            ->delete(route('admin.roles.destroy', $adminRole))
            ->assertRedirect(route('admin.roles.index'));

        $this->assertSame('active', $superAdmin->fresh()->status);
        $this->assertTrue($superAdmin->fresh()->isSuperAdmin());
        $this->assertDatabaseMissing('roles', ['id' => $adminRole->id]);
    }

    public function test_user_creation_is_atomic_and_audit_never_contains_plain_password(): void
    {
        $admin = $this->userWithRole('admin');
        $orderRole = Role::where('name', 'order_manager')->firstOrFail();
        $plainPassword = 'Strong!Password123';

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Integrated Operator',
                'username' => 'integrated.operator',
                'email' => 'integrated.operator@example.test',
                'password' => $plainPassword,
                'password_confirmation' => $plainPassword,
                'must_change_password' => '1',
                'role_ids' => [$orderRole->id],
            ])
            ->assertRedirect();

        $created = User::where('email', 'integrated.operator@example.test')->firstOrFail();
        $this->assertTrue(Hash::check($plainPassword, $created->password));
        $this->assertTrue($created->hasRole('order_manager'));
        $audit = AdminAuditLog::where('action', 'user.created')->where('subject_id', $created->id)->firstOrFail();
        $serializedAudit = json_encode([$audit->before_values, $audit->after_values]);
        $this->assertStringNotContainsString($plainPassword, $serializedAudit);
        $this->assertStringNotContainsString($created->password, $serializedAudit);
        $this->assertSame('[REDACTED]', $audit->after_values['password_set']);
    }

    public function test_super_admin_bypass_applies_to_new_permission_while_policy_only_protects_super_admin_role(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $permission = Permission::create(['name' => 'reports.export', 'description' => 'Export reports']);
        $systemRole = Role::where('name', 'customer_support')->firstOrFail();
        $protectedRole = Role::where('name', Role::SUPER_ADMIN)->firstOrFail();

        $this->assertTrue($superAdmin->hasPermission($permission->name));
        $this->assertTrue($superAdmin->can($permission->name));
        $this->assertTrue($superAdmin->can('update', $systemRole));
        $this->assertTrue($superAdmin->can('delete', $systemRole));
        $this->assertFalse($superAdmin->can('update', $protectedRole));
        $this->assertFalse($superAdmin->can('delete', $protectedRole));
    }

    /** @param array<int, string> $permissions */
    private function customRole(string $name, array $permissions): Role
    {
        $role = Role::create([
            'name' => $name,
            'is_admin' => true,
            'is_system' => false,
        ]);
        $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));

        return $role;
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }

    private function expectIamException(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected an IAM operation exception.');
        } catch (IamOperationException) {
            $this->assertTrue(true);
        }
    }
}
