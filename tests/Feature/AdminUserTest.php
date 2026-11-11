<?php

namespace Tests\Feature;

use App\Exceptions\IamOperationException;
use App\Models\AdminAuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\IamService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = $this->userWithRole('admin');
        $this->actingAs($this->admin);
    }

    public function test_admin_user_list_is_paginated_excludes_customers_and_eager_loads_roles(): void
    {
        $orderRole = Role::where('name', 'order_manager')->firstOrFail();
        User::factory()->count(16)->create()->each(fn (User $user) => $user->roles()->attach($orderRole));
        $customer = User::factory()->create();

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee($customer->email)
            ->assertViewHas('users', fn ($users) => $users->total() === 17
                && $users->count() === 15
                && $users->getCollection()->every(fn (User $user) => $user->relationLoaded('roles')));
    }

    public function test_admin_users_can_be_searched_and_filtered_by_status_and_role(): void
    {
        $orderRole = Role::where('name', 'order_manager')->firstOrFail();
        $matched = User::factory()->create([
            'name' => 'Matched Admin',
            'username' => 'matched.admin',
            'email' => 'matched-admin@example.test',
            'phone' => '0901234567',
            'status' => 'blocked',
        ]);
        $matched->roles()->attach($orderRole);
        $this->userWithRole('order_manager');

        $this->get(route('admin.users.index', [
            'q' => '0901234567',
            'status' => 'blocked',
            'role_id' => $orderRole->id,
        ]))
            ->assertOk()
            ->assertSee('Matched Admin')
            ->assertViewHas('users', fn ($users) => $users->total() === 1 && $users->first()->is($matched));
    }

    public function test_admin_user_filters_are_validated(): void
    {
        $nonAdminRole = Role::create(['name' => 'loyalty_member', 'is_admin' => false]);

        $this->get(route('admin.users.index', ['status' => 'unknown']))
            ->assertSessionHasErrors('status');
        $this->get(route('admin.users.index', ['role_id' => $nonAdminRole->id]))
            ->assertSessionHasErrors('role_id');
        $this->get(route('admin.users.index', ['role_id' => 999999]))
            ->assertSessionHasErrors('role_id');
    }

    public function test_admin_user_detail_shows_profile_roles_effective_permissions_and_recent_audit(): void
    {
        $target = $this->userWithRole('order_manager');
        $target->roles()->attach(Role::where('name', 'customer_support')->firstOrFail());
        $target->update([
            'last_login_at' => '2026-10-30 09:30:00',
            'must_change_password' => true,
        ]);
        AdminAuditLog::create([
            'actor_id' => $this->admin->id,
            'action' => 'user.roles_synced',
            'subject_type' => User::class,
            'subject_id' => $target->id,
            'before_values' => ['roles' => ['order_manager']],
            'after_values' => ['roles' => ['order_manager', 'customer_support']],
        ]);

        $this->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee($target->display_name)
            ->assertSee('order_manager')
            ->assertSee('customer_support')
            ->assertSee('orders.update')
            ->assertSee('contacts.update')
            ->assertSee('30/10/2026 09:30:00')
            ->assertSee('user.roles_synced')
            ->assertSee($this->admin->email)
            ->assertViewHas('effectivePermissions', fn ($permissions) => $permissions->contains('orders.update')
                && $permissions->contains('contacts.update'))
            ->assertViewHas('recentAudits', fn ($audits) => $audits->count() === 1
                && $audits->first()->relationLoaded('actor'));
    }

    public function test_admin_user_detail_hides_customers_and_unknown_users(): void
    {
        $customer = User::factory()->create();

        $this->get(route('admin.users.show', $customer))->assertNotFound();
        $this->get('/admin/users/999999')->assertNotFound();
    }

    public function test_admin_user_routes_and_navigation_require_users_view_permission(): void
    {
        $target = $this->userWithRole('order_manager');
        $catalogManager = $this->userWithRole('catalog_manager');

        $this->actingAs($catalogManager)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($catalogManager)->get(route('admin.users.show', $target))->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.users.index'), false);

        $this->actingAs($target)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.users.index'), false);
    }

    public function test_admin_can_create_account_with_assignable_role_and_safe_audit(): void
    {
        $role = Role::where('name', 'order_manager')->firstOrFail();
        $password = 'Strong!Password123';

        $this->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Tạo tài khoản quản trị');

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Order Operator',
            'username' => 'order_operator',
            'email' => 'ORDER.OPERATOR@example.test',
            'phone' => '0900000001',
            'password' => $password,
            'password_confirmation' => $password,
            'must_change_password' => '1',
            'role_ids' => [$role->id],
        ]);

        $response->assertSessionHasNoErrors();

        $created = User::where('username', 'order_operator')->firstOrFail();
        $response->assertRedirect(route('admin.users.show', $created));
        $this->assertTrue(Hash::check($password, $created->password));
        $this->assertTrue($created->must_change_password);
        $this->assertSame('active', $created->status);
        $this->assertTrue($created->hasRole('order_manager'));

        $audit = AdminAuditLog::where('action', 'user.created')->where('subject_id', $created->id)->firstOrFail();
        $this->assertSame($this->admin->id, $audit->actor_id);
        $this->assertStringNotContainsString($password, json_encode($audit->after_values));
        $this->assertArrayNotHasKey('password', $audit->after_values['profile']);
    }

    public function test_create_validates_unique_identity_strong_confirmed_password_and_role(): void
    {
        $role = Role::where('name', 'order_manager')->firstOrFail();
        $existing = User::factory()->create(['username' => 'existing', 'email' => 'existing@example.test']);

        $this->from(route('admin.users.create'))->post(route('admin.users.store'), [
            'name' => 'Invalid Admin',
            'username' => $existing->username,
            'email' => $existing->email,
            'password' => 'weak',
            'password_confirmation' => 'different',
            'role_ids' => [$role->id, $role->id],
        ])->assertRedirect(route('admin.users.create'))
            ->assertSessionHasErrors(['username', 'email', 'password', 'role_ids.1']);
    }

    public function test_admin_can_update_profile_roles_and_status_without_changing_password(): void
    {
        $target = $this->userWithRole('order_manager');
        $support = Role::where('name', 'customer_support')->firstOrFail();
        $oldPassword = $target->password;

        $this->get(route('admin.users.edit', $target))
            ->assertOk()
            ->assertSee('Cập nhật tài khoản quản trị');

        $this->put(route('admin.users.update', $target), [
            'name' => 'Updated Operator',
            'username' => $target->username,
            'email' => $target->email,
            'phone' => '0911111111',
            'status' => 'blocked',
            'password' => '',
            'password_confirmation' => '',
            'role_ids' => [$support->id],
        ])->assertRedirect(route('admin.users.show', $target));

        $target->refresh();
        $this->assertSame('Updated Operator', $target->name);
        $this->assertSame('blocked', $target->status);
        $this->assertSame($oldPassword, $target->password);
        $this->assertTrue($target->hasRole('customer_support'));
        $this->assertFalse($target->hasRole('order_manager'));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.updated', 'subject_id' => $target->id]);
    }

    public function test_actor_cannot_deactivate_or_change_their_own_roles(): void
    {
        $support = Role::where('name', 'customer_support')->firstOrFail();
        $payload = [
            'name' => $this->admin->name,
            'username' => $this->admin->username,
            'email' => $this->admin->email,
            'status' => 'blocked',
            'role_ids' => [$support->id],
        ];

        $response = $this->put(route('admin.users.update', $this->admin), $payload);
        $response
            ->assertSessionHasErrors('role_ids');

        $this->admin->refresh();
        $this->assertSame('active', $this->admin->status);
        $this->assertTrue($this->admin->hasRole('admin'));
    }

    public function test_only_super_admin_can_assign_or_remove_super_admin_role(): void
    {
        $superRole = Role::where('name', Role::SUPER_ADMIN)->firstOrFail();
        $orderRole = Role::where('name', 'order_manager')->firstOrFail();
        $target = $this->userWithRole('order_manager');

        $payload = [
            'name' => $target->name,
            'username' => $target->username,
            'email' => $target->email,
            'status' => 'active',
            'role_ids' => [$superRole->id],
        ];

        $this->put(route('admin.users.update', $target), $payload)->assertSessionHasErrors('role_ids');
        $this->assertFalse($target->fresh()->isSuperAdmin());

        $super = $this->userWithRole(Role::SUPER_ADMIN);
        $this->actingAs($super)->put(route('admin.users.update', $target), $payload)
            ->assertRedirect(route('admin.users.show', $target));
        $this->assertTrue($target->fresh()->isSuperAdmin());

        $this->actingAs($this->admin)->put(route('admin.users.update', $target), [
            ...$payload,
            'role_ids' => [$orderRole->id],
        ])->assertForbidden();
        $this->assertTrue($target->fresh()->isSuperAdmin());
    }

    public function test_non_admin_target_is_hidden_from_edit_and_update(): void
    {
        $customer = User::factory()->create();

        $this->get(route('admin.users.edit', $customer))->assertNotFound();
        $this->put(route('admin.users.update', $customer), [
            'name' => $customer->name,
            'username' => $customer->username,
            'email' => $customer->email,
            'status' => 'active',
            'role_ids' => [Role::where('name', 'order_manager')->value('id')],
        ])->assertNotFound();
    }

    public function test_admin_can_delete_unused_administrator_and_audit_is_preserved(): void
    {
        $target = $this->userWithRole('order_manager');
        $targetId = $target->id;

        $this->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee(route('admin.users.destroy', $target), false)
            ->assertSee('Xóa / khóa');

        $this->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $targetId]);
        $this->assertDatabaseMissing('user_roles', ['user_id' => $targetId]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'user.deleted',
            'subject_type' => User::class,
            'subject_id' => $targetId,
        ]);
    }

    public function test_delete_blocks_account_instead_when_business_traces_exist(): void
    {
        $target = $this->userWithRole('order_manager');
        AdminAuditLog::create([
            'actor_id' => $target->id,
            'action' => 'orders.status_updated',
            'subject_type' => User::class,
            'subject_id' => $target->id,
            'before_values' => [],
            'after_values' => [],
        ]);

        $this->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.show', $target))
            ->assertSessionHas('warning');

        $target->refresh();
        $this->assertSame('blocked', $target->status);
        $this->assertTrue($target->hasRole('order_manager'));
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'user.blocked_instead_of_deleted',
            'subject_id' => $target->id,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $target->id,
            'action' => 'orders.status_updated',
        ]);
    }

    public function test_delete_rejects_self_customer_and_actor_without_permission(): void
    {
        $customer = User::factory()->create();
        $catalogManager = $this->userWithRole('catalog_manager');
        $target = $this->userWithRole('order_manager');

        $this->delete(route('admin.users.destroy', $this->admin))->assertForbidden();
        $this->delete(route('admin.users.destroy', $customer))->assertNotFound();
        $this->actingAs($catalogManager)
            ->delete(route('admin.users.destroy', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
        $this->assertDatabaseHas('users', ['id' => $customer->id]);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_non_super_admin_cannot_delete_super_admin(): void
    {
        $target = $this->userWithRole(Role::SUPER_ADMIN);

        $this->delete(route('admin.users.destroy', $target))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_service_protects_last_active_super_admin_and_allows_deleting_another_one(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $target = $this->userWithRole(Role::SUPER_ADMIN);
        $service = app(IamService::class);

        $result = $service->deleteAdminUser($actor, $target);
        $this->assertSame('deleted', $result['outcome']);
        $this->assertDatabaseMissing('users', ['id' => $target->id]);

        $inactiveActor = $this->userWithRole(Role::SUPER_ADMIN);
        $inactiveActor->update(['status' => 'inactive']);

        $this->expectException(IamOperationException::class);
        $this->expectExceptionMessage('Hệ thống phải luôn còn ít nhất một super admin đang hoạt động.');

        $service->deleteAdminUser($inactiveActor, $actor);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }
}
