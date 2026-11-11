<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\PermissionMatrixService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AdminRolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_create_and_edit_pages_group_every_permission_once_and_render_module_controls(): void
    {
        $role = $this->customRole('warehouse_operator');

        $groups = app(PermissionMatrixService::class)->grouped();
        $groupedIds = $groups->flatMap(fn (array $group) => $group['permissions']->pluck('id'));

        $this->assertSame(Permission::count(), $groupedIds->count());
        $this->assertSame(Permission::count(), $groupedIds->unique()->count());
        $this->assertSame(
            ['dashboard', 'catalog', 'orders', 'customers', 'contacts', 'users', 'roles', 'settings', 'menus'],
            $groups->keys()->all(),
        );

        $this->actingAs($this->userWithRole(Role::SUPER_ADMIN))
            ->get(route('admin.roles.create'))
            ->assertOk()
            ->assertSee('Ma trận permission')
            ->assertSee('permission-search', false)
            ->assertSee('accordion-collapse collapse', false)
            ->assertSee('role-permission-matrix.js');

        $this->actingAs($this->userWithRole(Role::SUPER_ADMIN))
            ->get(route('admin.roles.edit', $role))
            ->assertOk()
            ->assertSee('Ma trận permission')
            ->assertSee('Cả module')
            ->assertSee('permission-select-all', false)
            ->assertSee('module-selected-count', false)
            ->assertSee('role-permission-matrix.js')
            ->assertSee('products.view')
            ->assertSee('permissions.manage');
    }

    public function test_super_admin_can_sync_permissions_and_change_is_audited(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $role = $this->customRole('warehouse_operator');
        $before = Permission::whereIn('name', ['dashboard.view', 'products.view'])->get();
        $after = Permission::whereIn('name', ['orders.view', 'orders.update'])->get();
        $role->permissions()->sync($before->pluck('id'));

        $this->actingAs($actor)
            ->put(route('admin.roles.permissions.update', $role), [
                'permission_ids' => $after->pluck('id')->all(),
            ])
            ->assertRedirect(route('admin.roles.edit', $role))
            ->assertSessionHas('success');

        $this->assertEqualsCanonicalizing($after->pluck('id')->all(), $role->permissions()->pluck('permissions.id')->all());
        $audit = AdminAuditLog::where('action', 'role.permissions_synced')->firstOrFail();
        $this->assertSame(['dashboard.view', 'products.view'], $audit->before_values['permissions']);
        $this->assertSame(['orders.update', 'orders.view'], $audit->after_values['permissions']);
        $this->assertSame($actor->id, $audit->actor_id);
    }

    public function test_empty_matrix_is_valid_but_unknown_or_duplicate_ids_are_rejected(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $role = $this->customRole('limited_operator');
        $permission = Permission::where('name', 'dashboard.view')->firstOrFail();
        $role->permissions()->attach($permission);

        $this->actingAs($actor)
            ->from(route('admin.roles.edit', $role))
            ->put(route('admin.roles.permissions.update', $role), ['permission_ids' => [999999]])
            ->assertRedirect(route('admin.roles.edit', $role))
            ->assertSessionHasErrors('permission_ids.0');
        $this->assertTrue($role->fresh()->permissions->contains($permission));

        $this->actingAs($actor)
            ->from(route('admin.roles.edit', $role))
            ->put(route('admin.roles.permissions.update', $role), ['permission_ids' => [$permission->id, $permission->id]])
            ->assertSessionHasErrors('permission_ids.1');
        $this->assertTrue($role->fresh()->permissions->contains($permission));

        $this->actingAs($actor)
            ->put(route('admin.roles.permissions.update', $role), ['permission_ids' => []])
            ->assertRedirect(route('admin.roles.edit', $role));
        $this->assertCount(0, $role->fresh()->permissions);
    }

    public function test_non_super_admin_cannot_change_roles_and_only_super_admin_system_role_is_immutable(): void
    {
        $customRole = $this->customRole('protected_custom');
        $systemRole = Role::where('name', 'admin')->firstOrFail();
        $protectedRole = Role::where('name', Role::SUPER_ADMIN)->firstOrFail();
        $permission = Permission::where('name', 'dashboard.view')->firstOrFail();

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.roles.permissions.update', $customRole), ['permission_ids' => [$permission->id]])
            ->assertForbidden();

        $this->actingAs($this->userWithRole(Role::SUPER_ADMIN))
            ->put(route('admin.roles.permissions.update', $systemRole), ['permission_ids' => []])
            ->assertRedirect(route('admin.roles.edit', $systemRole));

        $this->actingAs($this->userWithRole(Role::SUPER_ADMIN))
            ->put(route('admin.roles.permissions.update', $protectedRole), ['permission_ids' => []])
            ->assertForbidden();

        $this->assertFalse($customRole->fresh()->permissions->contains($permission));
        $this->assertTrue($systemRole->fresh()->permissions->isEmpty());
        $this->assertTrue($protectedRole->fresh()->permissions->isNotEmpty());
        $this->assertDatabaseCount('admin_audit_logs', 1);
    }

    public function test_syncing_an_assigned_custom_role_does_not_lock_out_super_admin(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $role = $this->customRole('secondary_governance');
        $role->permissions()->sync(Permission::whereIn('name', ['roles.manage', 'permissions.manage'])->pluck('id'));
        $actor->roles()->attach($role);

        $this->actingAs($actor)
            ->put(route('admin.roles.permissions.update', $role), ['permission_ids' => []])
            ->assertRedirect(route('admin.roles.edit', $role));

        $this->assertTrue($actor->fresh()->isSuperAdmin());
        $this->assertTrue($actor->fresh()->hasPermission('permissions.manage'));
        $this->assertCount(0, $role->fresh()->permissions);
    }

    public function test_permission_sync_rolls_back_when_audit_write_fails(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $role = $this->customRole('rollback_role');
        $before = Permission::where('name', 'dashboard.view')->firstOrFail();
        $after = Permission::where('name', 'orders.view')->firstOrFail();
        $role->permissions()->attach($before);

        $audit = $this->mock(AdminAuditService::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));

        $this->actingAs($actor)
            ->put(route('admin.roles.permissions.update', $role), ['permission_ids' => [$after->id]])
            ->assertServerError();

        $this->assertEquals([$before->id], $role->permissions()->pluck('permissions.id')->all());
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_unified_role_form_validates_permission_ids(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $role = $this->customRole('validated_role');
        $permission = Permission::where('name', 'dashboard.view')->firstOrFail();

        $this->actingAs($actor)
            ->post(route('admin.roles.store'), [
                'name' => 'invalid_permissions',
                'permission_ids' => [999999],
            ])
            ->assertSessionHasErrors('permission_ids.0');
        $this->assertDatabaseMissing('roles', ['name' => 'invalid_permissions']);

        $this->actingAs($actor)
            ->put(route('admin.roles.update', $role), [
                'name' => 'validated_role',
                'permission_ids' => [$permission->id, $permission->id],
            ])
            ->assertSessionHasErrors('permission_ids.1');
        $this->assertCount(0, $role->fresh()->permissions);
    }

    public function test_unified_create_and_update_roll_back_role_and_permissions_when_audit_fails(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $permission = Permission::where('name', 'orders.view')->firstOrFail();
        $role = $this->customRole('rollback_unified');
        $before = Permission::where('name', 'dashboard.view')->firstOrFail();
        $role->permissions()->attach($before);

        $audit = $this->mock(AdminAuditService::class);
        $audit->shouldReceive('record')->twice()->andThrow(new RuntimeException('Audit unavailable'));

        $this->actingAs($actor)
            ->post(route('admin.roles.store'), [
                'name' => 'rollback_created_role',
                'permission_ids' => [$permission->id],
            ])
            ->assertServerError();
        $this->assertDatabaseMissing('roles', ['name' => 'rollback_created_role']);

        $this->actingAs($actor)
            ->put(route('admin.roles.update', $role), [
                'name' => 'changed_but_rolled_back',
                'permission_ids' => [$permission->id],
            ])
            ->assertServerError();

        $role->refresh();
        $this->assertSame('rollback_unified', $role->name);
        $this->assertEquals([$before->id], $role->permissions()->pluck('permissions.id')->all());
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    private function customRole(string $name): Role
    {
        return Role::create([
            'name' => $name,
            'description' => 'Role test ma trận quyền',
            'is_admin' => true,
            'is_system' => false,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }
}
