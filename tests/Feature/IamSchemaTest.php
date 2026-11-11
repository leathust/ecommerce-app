<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IamSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_iam_schema_and_model_casts_are_available(): void
    {
        $this->assertTrue(Schema::hasColumns('roles', ['is_admin', 'is_system']));
        $this->assertTrue(Schema::hasColumns('users', ['must_change_password', 'last_login_at']));
        $this->assertTrue(Schema::hasColumns('admin_audit_logs', [
            'actor_id', 'action', 'subject_type', 'subject_id', 'before_values',
            'after_values', 'ip_address', 'user_agent', 'created_at',
        ]));

        $role = Role::create([
            'name' => 'custom_operator',
            'description' => 'Custom operator',
            'is_admin' => true,
            'is_system' => false,
        ]);
        $user = User::factory()->create([
            'must_change_password' => true,
            'last_login_at' => '2026-10-30 08:00:00',
        ]);

        $this->assertTrue($role->is_admin);
        $this->assertFalse($role->is_system);
        $this->assertTrue($user->must_change_password);
        $this->assertSame('2026-10-30 08:00:00', $user->last_login_at->format('Y-m-d H:i:s'));
    }

    public function test_role_permission_seeder_backfills_system_roles_and_new_security_matrix(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $systemRoles = Role::whereIn('name', Role::ADMIN_ROLES)->get();
        $this->assertCount(count(Role::ADMIN_ROLES), $systemRoles);
        $this->assertTrue($systemRoles->every(fn (Role $role) => $role->is_admin && $role->is_system));
        $this->assertDatabaseHas('permissions', ['name' => 'audit.view']);

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $this->assertTrue($admin->hasPermission('users.update'));
        $this->assertTrue($admin->hasPermission('roles.view'));
        $this->assertTrue($admin->hasPermission('audit.view'));
        $this->assertFalse($admin->hasPermission('roles.manage'));
        $this->assertFalse($admin->hasPermission('permissions.manage'));

        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach(Role::where('name', Role::SUPER_ADMIN)->firstOrFail());
        $this->assertTrue($superAdmin->hasPermission('permissions.manage'));
    }

    public function test_seeder_does_not_overwrite_custom_roles(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $permission = Permission::where('name', 'orders.view')->firstOrFail();
        $customRole = Role::create([
            'name' => 'custom_reviewer',
            'description' => 'Custom reviewer',
            'is_admin' => true,
            'is_system' => false,
        ]);
        $customRole->permissions()->attach($permission);

        $this->seed(RolePermissionSeeder::class);

        $customRole->refresh();
        $this->assertTrue($customRole->is_admin);
        $this->assertFalse($customRole->is_system);
        $this->assertTrue($customRole->permissions()->whereKey($permission)->exists());
    }

    public function test_audit_log_casts_relations_and_actor_deletion_are_safe(): void
    {
        $actor = User::factory()->create();
        $subject = Role::create(['name' => 'audited_role', 'description' => 'Before']);
        $audit = AdminAuditLog::create([
            'actor_id' => $actor->id,
            'action' => 'role.updated',
            'subject_type' => Role::class,
            'subject_id' => $subject->id,
            'before_values' => ['description' => 'Before'],
            'after_values' => ['description' => 'After'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->assertTrue($audit->actor->is($actor));
        $this->assertTrue($audit->subject->is($subject));
        $this->assertSame('Before', $audit->before_values['description']);
        $this->assertTrue($actor->adminAuditLogs->first()->is($audit));

        $actor->delete();

        $this->assertDatabaseHas('admin_audit_logs', ['id' => $audit->id, 'actor_id' => null]);
    }

    public function test_iam_migration_preserves_existing_users_roles_and_backfills_flags(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $adminRoleId = Role::where('name', 'admin')->value('id');
        $migration = require database_path('migrations/2026_10_30_000010_add_iam_fields_and_admin_audit_logs.php');

        $migration->down();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
        $this->assertDatabaseHas('roles', ['id' => $adminRoleId, 'name' => 'admin']);
        $this->assertFalse(Schema::hasTable('admin_audit_logs'));

        $migration->up();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'must_change_password' => false]);
        $this->assertDatabaseHas('roles', [
            'id' => $adminRoleId,
            'name' => 'admin',
            'is_admin' => true,
            'is_system' => true,
        ]);
        $this->assertTrue(Schema::hasTable('admin_audit_logs'));
        $this->assertSame(5, DB::table('roles')->where('is_admin', true)->count());
    }
}
