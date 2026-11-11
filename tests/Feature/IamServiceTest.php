<?php

namespace Tests\Feature;

use App\Exceptions\IamOperationException;
use App\Models\AdminAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\IamService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class IamServiceTest extends TestCase
{
    use RefreshDatabase;

    private IamService $iam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->iam = app(IamService::class);

        Route::middleware(['web', 'admin'])
            ->get('/test/custom-admin-role', fn () => 'allowed');
    }

    public function test_dynamic_admin_role_controls_admin_and_customer_scopes(): void
    {
        $role = Role::create([
            'name' => 'custom_admin',
            'is_admin' => true,
            'is_system' => false,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->assertTrue($user->isAdmin());
        $this->assertTrue(User::administrators()->whereKey($user)->exists());
        $this->assertFalse(User::customers()->whereKey($user)->exists());
        $this->actingAs($user)->get('/test/custom-admin-role')->assertOk();
    }

    public function test_effective_permissions_use_preloaded_relations_without_extra_queries(): void
    {
        $user = $this->userWithRole('order_manager')->load('roles.permissions');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $permissions = $user->effectivePermissionNames();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertContains('orders.view', $permissions);
        $this->assertContains('orders.update', $permissions);
        $this->assertNotContains('products.update', $permissions);
        $this->assertCount(0, $queries);
    }

    public function test_admin_can_sync_allowed_roles_and_change_is_audited(): void
    {
        $actor = $this->userWithRole('admin');
        $target = $this->userWithRole('order_manager');
        $catalogRole = Role::where('name', 'catalog_manager')->firstOrFail();

        $updated = $this->iam->syncUserRoles($actor, $target, [$catalogRole->id]);

        $this->assertTrue($updated->hasRole('catalog_manager'));
        $this->assertFalse($updated->hasRole('order_manager'));
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'user.roles_synced',
            'subject_type' => User::class,
            'subject_id' => $target->id,
        ]);
        $audit = AdminAuditLog::latest('id')->firstOrFail();
        $this->assertSame(['order_manager'], $audit->before_values['roles']);
        $this->assertSame(['catalog_manager'], $audit->after_values['roles']);
    }

    public function test_role_sync_rejects_invalid_or_privileged_roles_without_partial_changes(): void
    {
        $actor = $this->userWithRole('admin');
        $target = $this->userWithRole('order_manager');
        $superRole = Role::where('name', Role::SUPER_ADMIN)->firstOrFail();

        foreach ([[999999], [$superRole->id]] as $roleIds) {
            try {
                $this->iam->syncUserRoles($actor, $target, $roleIds);
                $this->fail('IAM service should reject invalid or privileged roles.');
            } catch (IamOperationException) {
                $this->assertTrue($target->fresh()->hasRole('order_manager'));
            }
        }

        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_last_active_super_admin_cannot_be_deactivated(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN, 'blocked');
        $target = $this->userWithRole(Role::SUPER_ADMIN, 'active');

        try {
            $this->iam->updateUserStatus($actor, $target, 'blocked');
            $this->fail('Last active super admin should be protected.');
        } catch (IamOperationException $exception) {
            $this->assertSame(
                'Hệ thống phải luôn còn ít nhất một super admin đang hoạt động.',
                $exception->getMessage(),
            );
        }

        $this->assertSame('active', $target->fresh()->status);
        $this->assertDatabaseCount('admin_audit_logs', 0);

        $actor->update(['status' => 'active']);
        $this->iam->updateUserStatus($actor, $target, 'blocked');
        $this->assertSame('blocked', $target->fresh()->status);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.status_updated']);
    }

    public function test_only_super_admin_can_sync_editable_role_permissions(): void
    {
        $admin = $this->userWithRole('admin');
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $customRole = Role::create(['name' => 'custom_editor', 'is_admin' => true, 'is_system' => false]);
        $systemRole = Role::where('name', 'catalog_manager')->firstOrFail();
        $protectedRole = Role::where('name', Role::SUPER_ADMIN)->firstOrFail();
        $permission = Permission::where('name', 'products.view')->firstOrFail();

        $this->expectIamException(fn () => $this->iam->syncRolePermissions($admin, $customRole, [$permission->id]));
        $this->expectIamException(fn () => $this->iam->syncRolePermissions($superAdmin, $protectedRole, [$permission->id]));

        $updatedSystemRole = $this->iam->syncRolePermissions($superAdmin, $systemRole, [$permission->id]);
        $this->assertTrue($updatedSystemRole->hasPermission('products.view'));

        $updated = $this->iam->syncRolePermissions($superAdmin, $customRole, [$permission->id]);
        $this->assertTrue($updated->hasPermission('products.view'));
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'role.permissions_synced',
            'subject_type' => Role::class,
            'subject_id' => $customRole->id,
        ]);
    }

    public function test_audit_sanitizer_and_policies_protect_sensitive_operations(): void
    {
        $audit = app(AdminAuditService::class);
        $sanitized = $audit->sanitize([
            'name' => 'Safe',
            'password' => 'PlainSecret',
            'nested' => ['api_token' => 'Hidden', 'api_key' => 'HiddenKey'],
        ]);
        $admin = $this->userWithRole('admin');
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $customRole = Role::create(['name' => 'policy_role', 'is_admin' => true, 'is_system' => false]);

        $this->assertSame('Safe', $sanitized['name']);
        $this->assertSame('[REDACTED]', $sanitized['password']);
        $this->assertSame('[REDACTED]', $sanitized['nested']['api_token']);
        $this->assertSame('[REDACTED]', $sanitized['nested']['api_key']);
        $this->assertFalse(Gate::forUser($admin)->allows('update', $superAdmin));
        $this->assertFalse(Gate::forUser($admin)->allows('update', $customRole));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', $customRole));
        $this->assertTrue(Gate::forUser($superAdmin)->allows(
            'update',
            Role::where('name', 'admin')->firstOrFail(),
        ));
        $this->assertFalse(Gate::forUser($superAdmin)->allows(
            'update',
            Role::where('name', Role::SUPER_ADMIN)->firstOrFail(),
        ));
    }

    private function userWithRole(string $roleName, string $status = 'active'): User
    {
        $user = User::factory()->create(['status' => $status]);
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
