<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\GoogleOAuthService;
use App\Services\SystemSettingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GoogleLoginSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Cache::flush();
    }

    public function test_super_admin_can_configure_and_toggle_google_login(): void
    {
        $actor = $this->superAdmin();

        $this->actingAs($actor)
            ->get(route('admin.settings.index', ['group' => 'auth']))
            ->assertOk()
            ->assertSee('Đăng nhập Google')
            ->assertSee('name="settings[auth.google_login_enabled]"', false)
            ->assertSee('data-setting-toggle', false)
            ->assertSee('setting-toggle__track', false)
            ->assertSee('name="settings[auth.google_client_id]"', false)
            ->assertSee('name="settings[auth.google_client_secret]"', false)
            ->assertSee('name="settings[auth.google_redirect_uri]"', false);

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'auth'), [
                'settings' => [
                    'auth.google_login_enabled' => '1',
                    'auth.google_client_id' => 'client-id-123',
                    'auth.google_client_secret' => 'client-secret-456',
                    'auth.google_redirect_uri' => 'https://shop.example.test/auth/google/callback',
                ],
            ])
            ->assertRedirect(route('admin.settings.index', ['group' => 'auth']));

        $this->assertDatabaseHas('settings', ['setting_key' => 'auth.google_login_enabled', 'setting_value' => '1']);
        $this->assertDatabaseHas('settings', ['setting_key' => 'auth.google_client_id', 'setting_value' => 'client-id-123']);
        $this->assertDatabaseHas('settings', ['setting_key' => 'auth.google_client_secret', 'setting_value' => 'client-secret-456']);

        $service = app(SystemSettingService::class);
        $this->assertTrue($service->get('auth.google_login_enabled'));
        $this->assertSame('client-id-123', $service->get('auth.google_client_id'));
        $this->assertSame('client-secret-456', $service->get('auth.google_client_secret'));
        $this->assertTrue(app(GoogleOAuthService::class)->enabled());

        $audit = AdminAuditLog::query()->where('action', 'settings.group_updated')->sole();
        $this->assertSame('[REDACTED]', $audit->after_values['values']['auth.google_client_secret']);
        $this->assertSame('client-id-123', $audit->after_values['values']['auth.google_client_id']);
    }

    public function test_blank_secret_keeps_the_stored_secret(): void
    {
        $actor = $this->superAdmin();

        app(SystemSettingService::class)->updateGroup($actor, 'auth', [
            'auth.google_login_enabled' => true,
            'auth.google_client_id' => 'client-id',
            'auth.google_client_secret' => 'original-secret',
        ]);

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'auth'), [
                'settings' => [
                    'auth.google_login_enabled' => '1',
                    'auth.google_client_id' => 'client-id',
                    'auth.google_client_secret' => '',
                ],
            ])
            ->assertRedirect(route('admin.settings.index', ['group' => 'auth']));

        $this->assertDatabaseHas('settings', [
            'setting_key' => 'auth.google_client_secret',
            'setting_value' => 'original-secret',
        ]);
    }

    public function test_settings_page_never_renders_the_stored_secret(): void
    {
        $actor = $this->superAdmin();

        app(SystemSettingService::class)->updateGroup($actor, 'auth', [
            'auth.google_client_id' => 'visible-client-id',
            'auth.google_client_secret' => 'MUST_NOT_APPEAR_IN_HTML',
        ]);

        $this->actingAs($actor)
            ->get(route('admin.settings.index', ['group' => 'auth']))
            ->assertOk()
            ->assertSee('visible-client-id')
            ->assertDontSee('MUST_NOT_APPEAR_IN_HTML');
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', Role::SUPER_ADMIN)->firstOrFail());

        return $user;
    }
}
