<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\SystemSettingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemSettingQualityGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Cache::flush();
    }

    public function test_permission_matrix_covers_super_admin_admin_viewer_and_admin_without_permission(): void
    {
        $superAdmin = $this->userWithRole(Role::SUPER_ADMIN);
        $admin = $this->userWithRole('admin');
        $viewer = $this->userWithPermissions('settings_quality_viewer', ['settings.view']);
        $denied = $this->userWithRole('catalog_manager');

        $this->actingAs($superAdmin)->get(route('admin.settings.index'))->assertOk();

        $this->actingAs($admin)
            ->put(route('admin.settings.update', 'contact'), [
                'settings' => [
                    'contact.email' => 'admin-updated@example.test',
                    'contact.phone' => '0901234567',
                ],
            ])
            ->assertRedirect(route('admin.settings.index', ['group' => 'contact']));

        $this->actingAs($viewer)->get(route('admin.settings.index'))->assertOk();
        $this->actingAs($viewer)
            ->put(route('admin.settings.update', 'contact'), [
                'settings' => ['contact.email' => 'forbidden@example.test'],
            ])
            ->assertForbidden();

        $this->actingAs($denied)->get(route('admin.settings.index'))->assertForbidden();
        $this->assertDatabaseHas('settings', [
            'setting_key' => 'contact.email',
            'setting_value' => 'admin-updated@example.test',
            'updated_by' => $admin->id,
        ]);
    }

    public function test_multi_field_update_invalidates_cache_and_is_visible_on_the_next_request(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $settings = app(SystemSettingService::class);

        $this->assertSame('', $settings->get('contact.email'));

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'contact'), [
                'settings' => [
                    'contact.email' => 'fresh@example.test',
                    'contact.phone' => '028 1234 5678',
                    'contact.address_vi' => 'Địa chỉ mới sau khi xóa cache',
                ],
            ])
            ->assertRedirect(route('admin.settings.index', ['group' => 'contact']));

        $this->assertSame('fresh@example.test', $settings->get('contact.email'));
        $this->assertSame('028 1234 5678', $settings->get('contact.phone'));

        $this->actingAs($actor)
            ->get(route('admin.settings.index', ['group' => 'contact']))
            ->assertOk()
            ->assertSee('fresh@example.test')
            ->assertSee('028 1234 5678')
            ->assertSee('Địa chỉ mới sau khi xóa cache');

        $audit = AdminAuditLog::query()->where('action', 'settings.group_updated')->sole();
        $this->assertCount(3, $audit->after_values['values']);
        $this->assertDatabaseCount('admin_audit_logs', 1);
    }

    public function test_admin_settings_request_queries_settings_table_at_most_once(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        Setting::query()->create([
            'setting_key' => 'site.logo',
            'setting_value' => 'system-settings/logo/quality.png',
        ]);
        app(SystemSettingService::class)->forgetCache();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($actor)
            ->get(route('admin.settings.index', ['group' => 'site']))
            ->assertOk();

        $settingsQueries = collect(DB::getQueryLog())->filter(
            static fn (array $query): bool => str_contains(strtolower($query['query']), 'from "settings"')
                || str_contains(strtolower($query['query']), 'from `settings`'),
        );

        $this->assertLessThanOrEqual(1, $settingsQueries->count());
    }

    public function test_infrastructure_credentials_are_not_registry_values_responses_or_audit_payloads(): void
    {
        $credential = 'QUALITY_GATE_CREDENTIAL_MUST_NOT_LEAK';
        config([
            'database.connections.mysql.password' => $credential,
            'mail.mailers.smtp.password' => $credential,
        ]);
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $definitions = config('system_settings.settings');

        foreach ($definitions as $key => $definition) {
            // Google OAuth credentials are an intentional, managed exception: they are
            // stored in settings, never public, and redacted from audit payloads.
            if (! str_starts_with($key, 'auth.google_')) {
                $this->assertDoesNotMatchRegularExpression(
                    '/password|token|secret|api[_-]?key|credential/i',
                    $key,
                );
            }
            $this->assertNotSame($credential, $definition['default']);
        }

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'mail'), [
                'settings' => [
                    'mail.from_name' => 'Quality Gate Mailer',
                    'mail.contact_recipient' => 'internal@example.test',
                ],
            ])
            ->assertRedirect(route('admin.settings.index', ['group' => 'mail']));

        $this->actingAs($actor)
            ->get(route('admin.settings.index', ['group' => 'mail']))
            ->assertOk()
            ->assertDontSee($credential);
        $this->get(route('home'))->assertOk()->assertDontSee($credential);

        $auditPayload = AdminAuditLog::query()->where('action', 'settings.group_updated')->sole()->toJson();
        $this->assertStringNotContainsString($credential, $auditPayload);
        $this->assertStringNotContainsString($credential, file_get_contents(base_path('docs/assets/pm-board/pm-board.js')));
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        return $user;
    }

    /** @param array<int, string> $permissionNames */
    private function userWithPermissions(string $roleName, array $permissionNames): User
    {
        $role = Role::query()->create([
            'name' => $roleName,
            'is_admin' => true,
            'is_system' => false,
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('name', $permissionNames)->pluck('id'));

        return $this->userWithRole($roleName);
    }
}
