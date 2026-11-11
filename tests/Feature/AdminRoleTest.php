<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $this->actingAs($this->superAdmin);
    }

    public function test_role_list_is_paginated_searchable_and_contains_counts(): void
    {
        foreach (range(1, 16) as $index) {
            Role::create([
                'name' => "custom_role_{$index}",
                'is_admin' => true,
                'is_system' => false,
            ]);
        }
        $matched = Role::create([
            'name' => 'warehouse_manager',
            'description' => 'Điều phối kho trung tâm',
            'is_admin' => true,
            'is_system' => false,
        ]);
        $matched->permissions()->attach(Permission::query()->firstOrFail());
        $this->userWithRole($matched->name);

        $this->get(route('admin.roles.index'))
            ->assertOk()
            ->assertViewHas('roles', fn ($roles) => $roles->total() === 22
                && $roles->count() === 15
                && $roles->getCollection()->every(fn (Role $role) => isset($role->users_count, $role->permissions_count)));

        $this->get(route('admin.roles.index', ['q' => 'kho trung tâm']))
            ->assertOk()
            ->assertSee('warehouse_manager')
            ->assertSee('Sửa &amp; phân quyền', false)
            ->assertSee(route('admin.roles.edit', $matched), false)
            ->assertViewHas('roles', fn ($roles) => $roles->total() === 1 && $roles->first()->is($matched));
    }

    public function test_super_admin_can_create_update_and_delete_custom_admin_role_with_audit(): void
    {
        $createPermissions = Permission::whereIn('name', ['dashboard.view', 'products.view'])->get();
        $updatePermissions = Permission::whereIn('name', ['orders.view', 'orders.update'])->get();

        $this->get(route('admin.roles.create'))
            ->assertOk()
            ->assertSee('Tạo vai trò quản trị')
            ->assertSee('Ma trận permission');

        $this->post(route('admin.roles.store'), [
            'name' => 'Warehouse Manager',
            'description' => 'Quản lý vận hành kho',
            'permission_ids' => $createPermissions->pluck('id')->all(),
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', 'warehouse_manager')->firstOrFail();
        $this->assertTrue($role->is_admin);
        $this->assertFalse($role->is_system);
        $this->assertEqualsCanonicalizing($createPermissions->pluck('id')->all(), $role->permissions()->pluck('permissions.id')->all());
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'role.created', 'subject_id' => $role->id]);
        $createdAudit = AdminAuditLog::where('action', 'role.created')->where('subject_id', $role->id)->firstOrFail();
        $this->assertSame(['dashboard.view', 'products.view'], $createdAudit->after_values['permissions']);

        $this->get(route('admin.roles.edit', $role))->assertOk();
        $this->put(route('admin.roles.update', $role), [
            'name' => 'Inventory Manager',
            'description' => 'Quản lý tồn kho',
            'permission_ids' => $updatePermissions->pluck('id')->all(),
        ])->assertRedirect(route('admin.roles.index'));

        $role->refresh();
        $this->assertSame('inventory_manager', $role->name);
        $this->assertSame('Quản lý tồn kho', $role->description);
        $this->assertEqualsCanonicalizing($updatePermissions->pluck('id')->all(), $role->permissions()->pluck('permissions.id')->all());
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'role.updated', 'subject_id' => $role->id]);
        $updatedAudit = AdminAuditLog::where('action', 'role.updated')->where('subject_id', $role->id)->firstOrFail();
        $this->assertSame(['dashboard.view', 'products.view'], $updatedAudit->before_values['permissions']);
        $this->assertSame(['orders.update', 'orders.view'], $updatedAudit->after_values['permissions']);

        $roleId = $role->id;
        $this->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'role.deleted', 'subject_id' => $roleId]);
    }

    public function test_reserved_duplicate_and_invalid_role_names_are_rejected(): void
    {
        Role::create(['name' => 'warehouse_manager', 'is_admin' => true, 'is_system' => false]);

        $this->post(route('admin.roles.store'), ['name' => 'admin'])
            ->assertSessionHasErrors('name');
        $this->post(route('admin.roles.store'), ['name' => 'Warehouse Manager'])
            ->assertSessionHasErrors('name');
        $this->post(route('admin.roles.store'), ['name' => '123 manager'])
            ->assertSessionHasErrors('name');

        $this->assertSame(6, Role::query()->count());
    }

    public function test_only_super_admin_role_is_locked_while_other_unused_system_roles_are_editable_and_deletable(): void
    {
        $systemRole = Role::where('name', 'admin')->firstOrFail();
        $protectedRole = Role::where('name', Role::SUPER_ADMIN)->firstOrFail();
        // Regression: legacy/corrupted flags must not turn a reserved role into a custom role.
        $systemRole->update(['is_system' => false]);
        $customRole = Role::create([
            'name' => 'warehouse_manager',
            'is_admin' => true,
            'is_system' => false,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($customRole);

        $permission = Permission::where('name', 'dashboard.view')->firstOrFail();

        $this->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee(route('admin.roles.edit', $systemRole), false)
            ->assertSee(route('admin.roles.destroy', $systemRole), false)
            ->assertDontSee(route('admin.roles.edit', $protectedRole), false)
            ->assertSee('Bảo vệ tuyệt đối');
        $this->get(route('admin.roles.edit', $systemRole))
            ->assertOk()
            ->assertSee('System admin role')
            ->assertSee('Tên role hệ thống không thể thay đổi.')
            ->assertSee('readonly', false);
        $this->put(route('admin.roles.update', $systemRole), [
            'name' => 'admin',
            'description' => 'Quản trị viên đã điều chỉnh',
            'permission_ids' => [$permission->id],
        ])->assertRedirect(route('admin.roles.index'));

        $systemRole->refresh();
        $this->assertSame('admin', $systemRole->name);
        $this->assertSame('Quản trị viên đã điều chỉnh', $systemRole->description);
        $this->assertEquals([$permission->id], $systemRole->permissions()->pluck('permissions.id')->all());

        $this->put(route('admin.roles.update', $systemRole), [
            'name' => 'renamed_admin',
            'permission_ids' => [],
        ])->assertSessionHasErrors('name');
        $this->get(route('admin.roles.edit', $protectedRole))->assertForbidden();
        $this->delete(route('admin.roles.destroy', $systemRole))
            ->assertRedirect(route('admin.roles.index'));
        $this->delete(route('admin.roles.destroy', $protectedRole))->assertForbidden();
        $this->delete(route('admin.roles.destroy', $customRole))
            ->assertRedirect()
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseMissing('roles', ['id' => $systemRole->id]);
        $this->assertDatabaseHas('roles', ['id' => $customRole->id]);
        $this->assertDatabaseHas('user_roles', ['user_id' => $user->id, 'role_id' => $customRole->id]);
    }

    public function test_role_permission_seeder_does_not_overwrite_customized_system_role_permissions(): void
    {
        $role = Role::where('name', 'admin')->firstOrFail();
        $permission = Permission::where('name', 'dashboard.view')->firstOrFail();
        $role->permissions()->sync([$permission->id]);

        $this->seed(RolePermissionSeeder::class);

        $this->assertEquals([$permission->id], $role->fresh()->permissions()->pluck('permissions.id')->all());
    }

    public function test_admin_can_view_roles_but_only_super_admin_can_manage_them(): void
    {
        $admin = $this->userWithRole('admin');
        $customRole = Role::create([
            'name' => 'warehouse_manager',
            'is_admin' => true,
            'is_system' => false,
        ]);

        $this->actingAs($admin)->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee(route('admin.roles.index'), false)
            ->assertSee('Không có quyền sửa')
            ->assertDontSee(route('admin.roles.create'), false);
        $this->actingAs($admin)->get(route('admin.roles.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.roles.store'), ['name' => 'new_role'])->assertForbidden();
        $this->actingAs($admin)->get(route('admin.roles.edit', $customRole))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.roles.destroy', $customRole))->assertForbidden();
    }

    public function test_custom_admin_role_is_recognized_without_static_role_list_changes(): void
    {
        $role = Role::create([
            'name' => 'warehouse_manager',
            'is_admin' => true,
            'is_system' => false,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isCustomer());
        $this->assertTrue(User::query()->administrators()->whereKey($user)->exists());
        $this->assertFalse(User::query()->customers()->whereKey($user)->exists());
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }
}
