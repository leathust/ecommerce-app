<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\SystemSettingAssetService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class SystemSettingUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_super_admin_can_upload_logo_and_preview_managed_asset(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $logo = $this->pngFile('logo.png');

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'site'), [
                'settings' => ['site.name_vi' => 'Cửa hàng Công nghệ'],
                'assets' => ['site.logo' => $logo],
            ])
            ->assertRedirect(route('admin.settings.index', ['group' => 'site']))
            ->assertSessionHas('success');

        $path = Setting::where('setting_key', 'site.logo')->value('setting_value');
        $this->assertIsString($path);
        $this->assertStringStartsWith('system-settings/logo/', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame($actor->id, Setting::where('setting_key', 'site.logo')->value('updated_by'));

        $audit = AdminAuditLog::where('action', 'settings.group_updated')->sole();
        $this->assertSame($path, $audit->after_values['values']['site.logo']);

        $this->actingAs($actor)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($path), false)
            ->assertSee('Xóa ảnh hiện tại');
    }

    public function test_replace_and_remove_delete_only_the_previous_managed_file(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $oldPath = 'system-settings/logo/old-logo.png';
        Storage::disk('public')->put($oldPath, 'old');
        Setting::create(['setting_key' => 'site.logo', 'setting_value' => $oldPath, 'updated_by' => $actor->id]);

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'site'), [
                'settings' => ['site.name_vi' => 'Cửa hàng'],
                'assets' => ['site.logo' => $this->pngFile('new-logo.png')],
            ])
            ->assertRedirect();

        $newPath = Setting::where('setting_key', 'site.logo')->value('setting_value');
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'site'), [
                'settings' => ['site.name_vi' => 'Cửa hàng'],
                'remove_assets' => ['site.logo' => '1'],
            ])
            ->assertRedirect();

        $this->assertNull(Setting::where('setting_key', 'site.logo')->value('setting_value'));
        Storage::disk('public')->assertMissing($newPath);
        $this->assertDatabaseCount('admin_audit_logs', 2);
    }

    public function test_remove_does_not_delete_external_or_unmanaged_paths(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $unmanagedPath = 'other-module/keep.png';
        Storage::disk('public')->put($unmanagedPath, 'keep');
        Setting::create([
            'setting_key' => 'site.logo',
            'setting_value' => $unmanagedPath,
            'updated_by' => $actor->id,
        ]);

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'site'), [
                'settings' => ['site.name_vi' => 'Cửa hàng'],
                'remove_assets' => ['site.logo' => '1'],
            ])
            ->assertRedirect();

        Storage::disk('public')->assertExists($unmanagedPath);
        $this->assertNull(Setting::where('setting_key', 'site.logo')->value('setting_value'));
        $this->assertNull(app(SystemSettingAssetService::class)->publicUrl(
            'site.logo',
            'https://cdn.example.test/logo.png',
        ));
    }

    public function test_invalid_cross_group_oversized_and_conflicting_assets_are_rejected(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'site'), [
                'settings' => ['site.name_vi' => 'Cửa hàng'],
                'assets' => ['site.logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')],
            ])
            ->assertSessionHasErrors('assets.site.logo');

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'site'), [
                'settings' => ['site.name_vi' => 'Cửa hàng'],
                'assets' => ['site.logo' => $this->pngFile('huge.png', 3000)],
            ])
            ->assertSessionHasErrors('assets.site.logo');

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'site'), [
                'settings' => ['site.name_vi' => 'Cửa hàng'],
                'assets' => ['seo.og_image' => $this->pngFile('og.png')],
            ])
            ->assertSessionHasErrors('assets');

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'site'), [
                'settings' => ['site.name_vi' => 'Cửa hàng'],
                'assets' => ['site.logo' => $this->pngFile('logo.png')],
                'remove_assets' => ['site.logo' => '1'],
            ])
            ->assertSessionHasErrors('assets.site.logo');

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_new_upload_is_removed_and_database_rolls_back_when_audit_fails(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $audit = $this->mock(AdminAuditService::class);
        $audit->shouldReceive('recordSystemEvent')->once()->andThrow(new RuntimeException('Audit unavailable'));

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'site'), [
                'settings' => ['site.name_vi' => 'Cửa hàng'],
                'assets' => ['site.logo' => $this->pngFile('logo.png')],
            ])
            ->assertServerError();

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('settings', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_actor_without_update_permission_cannot_store_assets(): void
    {
        $viewer = $this->userWithRole('catalog_manager');

        $this->actingAs($viewer)
            ->put(route('admin.settings.update', 'site'), [
                'settings' => ['site.name_vi' => 'Không được phép'],
                'assets' => ['site.logo' => $this->pngFile('logo.png')],
            ])
            ->assertForbidden();

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('settings', 0);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }

    private function pngFile(string $name, int $sizeKilobytes = 1): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        $targetBytes = max(strlen($png), $sizeKilobytes * 1024);

        return UploadedFile::fake()->createWithContent(
            $name,
            $png.str_repeat("\0", $targetBytes - strlen($png)),
        );
    }
}
