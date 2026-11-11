<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminAuditService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
    }

    public function test_audit_list_is_paginated_and_newest_event_is_first(): void
    {
        $role = Role::where('name', 'admin')->firstOrFail();

        foreach (range(1, 21) as $index) {
            $this->audit($this->superAdmin, 'role.updated', $role, "2026-11-01 10:00:{$index}");
        }

        $this->actingAs($this->superAdmin)
            ->get(route('admin.access-logs.index'))
            ->assertOk()
            ->assertViewHas('audits', fn ($audits) => $audits->total() === 21
                && $audits->count() === 20
                && $audits->first()->id > $audits->last()->id)
            ->assertSee('Nhật ký quản trị')
            ->assertSee('21 sự kiện');
    }

    public function test_audit_list_filters_actor_action_subject_and_date_range(): void
    {
        $otherActor = $this->userWithRole(Role::SUPER_ADMIN);
        $role = Role::where('name', 'admin')->firstOrFail();
        $user = $this->userWithRole('order_manager');
        $match = $this->audit($otherActor, 'role.updated', $role, '2026-11-03 09:00:00');
        $this->audit($this->superAdmin, 'role.updated', $role, '2026-11-03 10:00:00');
        $this->audit($otherActor, 'user.updated', $user, '2026-11-03 11:00:00');
        $this->audit($otherActor, 'role.updated', $role, '2026-10-20 09:00:00');

        $this->actingAs($this->superAdmin)
            ->get(route('admin.access-logs.index', [
                'actor_id' => $otherActor->id,
                'action' => 'role.updated',
                'subject_type' => 'role',
                'subject_id' => $role->id,
                'date_from' => '2026-11-01',
                'date_to' => '2026-11-05',
            ]))
            ->assertOk()
            ->assertViewHas('audits', fn ($audits) => $audits->total() === 1 && $audits->first()->is($match));
    }

    public function test_invalid_filters_are_rejected_without_accepting_arbitrary_subject_classes(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.access-logs.index', [
                'actor_id' => 999999,
                'action' => 'unknown.action',
                'subject_type' => User::class,
                'date_from' => '2026-11-05',
                'date_to' => '2026-11-01',
            ]))
            ->assertSessionHasErrors(['actor_id', 'action', 'subject_type', 'date_to']);
    }

    public function test_detail_uses_allowlist_redacts_secrets_and_escapes_html(): void
    {
        $role = Role::where('name', 'admin')->firstOrFail();
        $audit = AdminAuditLog::create([
            'actor_id' => $this->superAdmin->id,
            'action' => 'role.updated',
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'before_values' => [
                'role' => ['name' => '<script>alert(1)</script>', 'api_token' => 'secret-token'],
                'unexpected_private_data' => 'must-not-render',
            ],
            'after_values' => ['password_changed' => true, 'password' => 'plain-secret'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Audit test browser',
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.access-logs.show', $audit))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('[REDACTED]')
            ->assertDontSee('secret-token')
            ->assertDontSee('plain-secret')
            ->assertDontSee('must-not-render')
            ->assertSee('127.0.0.1')
            ->assertSee('Audit test browser');
    }

    public function test_only_permission_manager_can_open_audit_pages_and_navigation(): void
    {
        $admin = $this->userWithRole('admin');
        $catalog = $this->userWithRole('catalog_manager');
        $audit = $this->audit($this->superAdmin, 'role.updated', Role::where('name', 'admin')->firstOrFail());

        $this->actingAs($admin)->get(route('admin.access-logs.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.access-logs.show', $audit))->assertForbidden();
        $this->actingAs($catalog)->get(route('admin.access-logs.index'))->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.access-logs.index'))
            ->assertOk()
            ->assertSee(route('admin.access-logs.index'), false);
    }

    public function test_user_and_role_pages_link_to_filtered_audit_history(): void
    {
        $target = $this->userWithRole('order_manager');
        $role = Role::create(['name' => 'linked_role', 'is_admin' => true, 'is_system' => false]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee(route('admin.access-logs.index', ['subject_type' => 'user', 'subject_id' => $target->id]));

        $this->actingAs($this->superAdmin)
            ->get(route('admin.roles.edit', $role))
            ->assertOk()
            ->assertSee(route('admin.access-logs.index', ['subject_type' => 'role', 'subject_id' => $role->id]));
    }

    public function test_audit_service_sanitizes_values_on_write_and_read(): void
    {
        $service = app(AdminAuditService::class);

        $this->assertSame(
            ['profile' => ['email' => 'safe@example.test', 'password' => '[REDACTED]']],
            $service->safeForDisplay([
                'profile' => ['email' => 'safe@example.test', 'password' => 'unsafe'],
                'unknown' => 'hidden',
            ]),
        );
    }

    private function audit(
        User $actor,
        string $action,
        User|Role $subject,
        ?string $createdAt = null,
    ): AdminAuditLog {
        return AdminAuditLog::create([
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->id,
            'before_values' => ['status' => 'before'],
            'after_values' => ['status' => 'after'],
            'created_at' => $createdAt ?? now(),
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }
}
