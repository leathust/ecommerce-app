<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminSystemSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_grouped_settings_and_navigation(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);

        $response = $this->actingAs($actor)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Cài đặt hệ thống')
            ->assertSee('Website &amp; thương hiệu', false)
            ->assertSee('fa-solid fa-palette', false)
            ->assertSee('setting-field__icon', false)
            ->assertSee('data-settings-image-input', false)
            ->assertSee('data-settings-preview-image', false)
            ->assertSee('data-settings-preview-clear', false)
            ->assertSee('data-settings-preview-actions hidden', false)
            ->assertSee('URL.createObjectURL(file)', false)
            ->assertSee('data-settings-form', false)
            ->assertSee("Accept: 'application/json'", false)
            ->assertSee('showValidationErrors(payload.errors || {})', false)
            ->assertSee('name="settings[site.name_vi]"', false)
            ->assertSee('name="assets[site.logo]"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('Lưu Website &amp; thương hiệu', false)
            ->assertSee(route('admin.settings.index'), false);

        $this->assertStringNotContainsString('<code class="small">site.name_vi</code>', $response->getContent());

        $this->actingAs($actor)
            ->get(route('admin.settings.index', ['group' => 'commerce']))
            ->assertOk()
            ->assertSee('name="settings[commerce.default_shipping_fee]"', false)
            ->assertSee('Chuẩn bị')
            ->assertSee('Cấu hình này chưa tác động runtime');

        $this->assertContains(
            'permission:settings.view',
            Route::getRoutes()->getByName('admin.settings.index')->gatherMiddleware(),
        );
        $this->assertContains(
            'permission:settings.update',
            Route::getRoutes()->getByName('admin.settings.update')->gatherMiddleware(),
        );
    }

    public function test_view_only_admin_can_open_page_but_cannot_update(): void
    {
        $viewer = $this->userWithPermissions('settings_viewer', ['settings.view']);

        $this->actingAs($viewer)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Chỉ xem')
            ->assertDontSee('Lưu Website &amp; thương hiệu', false);

        $this->actingAs($viewer)
            ->put(route('admin.settings.update', 'site'), [
                'settings' => ['site.name_vi' => 'Không được lưu'],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('settings', 0);
    }

    public function test_ajax_update_returns_validation_errors_or_success_without_redirect(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);

        $this->actingAs($actor)
            ->putJson(route('admin.settings.update', 'contact'), [
                'settings' => ['contact.email' => 'email-khong-hop-le'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.contact.email');

        $this->actingAs($actor)
            ->putJson(route('admin.settings.update', 'contact'), [
                'settings' => ['contact.email' => 'support@example.test'],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Đã cập nhật nhóm cài đặt Liên hệ.')
            ->assertJsonStructure(['asset_urls']);

        $this->assertDatabaseHas('settings', [
            'setting_key' => 'contact.email',
            'setting_value' => 'support@example.test',
        ]);
    }

    public function test_super_admin_can_update_payment_email_template_settings(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'mail'), [
                'settings' => [
                    'mail.from_name' => 'Ecommerce Support',
                    'mail.payment_confirmation_subject_vi' => 'Đã nhận tiền đơn :order_code',
                    'mail.payment_confirmation_subject_en' => 'Paid order :order_code',
                    'mail.payment_confirmation_intro_vi' => 'Xin chào :name, đơn :order_code đã được thanh toán.',
                    'mail.payment_confirmation_intro_en' => 'Hello :name, order :order_code is paid.',
                    'mail.payment_confirmation_footer_vi' => 'Bộ phận chăm sóc khách hàng.',
                    'mail.payment_confirmation_footer_en' => 'Customer care team.',
                ],
            ])
            ->assertRedirect(route('admin.settings.index', ['group' => 'mail']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'setting_key' => 'mail.payment_confirmation_subject_vi',
            'setting_value' => 'Đã nhận tiền đơn :order_code',
            'updated_by' => $actor->id,
        ]);
        $this->actingAs($actor)
            ->get(route('admin.settings.index', ['group' => 'mail']))
            ->assertOk()
            ->assertSee('name="settings[mail.payment_confirmation_subject_vi]"', false)
            ->assertSee('Đã nhận tiền đơn :order_code', false)
            ->assertSee('name="settings[mail.payment_confirmation_intro_en]"', false);
    }

    public function test_admin_without_view_permission_cannot_open_page_or_see_navigation(): void
    {
        $catalogManager = $this->userWithRole('catalog_manager');

        $this->actingAs($catalogManager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.settings.index'), false);

        $this->actingAs($catalogManager)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    public function test_authorized_update_persists_group_and_creates_one_audit(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'site'), [
                'settings' => [
                    'site.name_vi' => 'Cửa hàng Công nghệ',
                    'site.name_en' => 'Technology Store',
                    'site.default_locale' => 'vi',
                    'site.display_timezone' => 'Asia/Ho_Chi_Minh',
                ],
            ])
            ->assertRedirect(route('admin.settings.index', ['group' => 'site']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'setting_key' => 'site.name_vi',
            'setting_value' => 'Cửa hàng Công nghệ',
            'updated_by' => $actor->id,
        ]);
        $audit = AdminAuditLog::where('action', 'settings.group_updated')->sole();
        $this->assertSame('Cập nhật cài đặt hệ thống', $audit->action_label);
        $this->assertSame('Cài đặt hệ thống', $audit->subject_label);
        $this->assertSame('site', $audit->after_values['group']);
    }

    public function test_dynamic_validation_rejects_invalid_cross_group_and_unknown_payload(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);

        $this->actingAs($actor)
            ->from(route('admin.settings.index', ['group' => 'contact']))
            ->put(route('admin.settings.update', 'contact'), [
                'settings' => ['contact.email' => 'khong-phai-email'],
            ])
            ->assertRedirect(route('admin.settings.index', ['group' => 'contact']))
            ->assertSessionHasErrors('settings.contact.email');

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'site'), [
                'settings' => ['contact.email' => 'support@example.test'],
            ])
            ->assertSessionHasErrors('settings');

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'unknown'), [
                'settings' => ['unknown.key' => 'value'],
            ])
            ->assertSessionHasErrors('settings');

        $this->get(route('admin.settings.index', ['group' => 'unknown']))->assertNotFound();
        $this->assertDatabaseCount('settings', 0);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_boolean_fields_are_normalized_when_checkbox_is_missing(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        Setting::create([
            'setting_key' => 'seo.indexing_enabled',
            'setting_value' => '1',
            'updated_by' => $actor->id,
        ]);

        $this->actingAs($actor)
            ->put(route('admin.settings.update', 'seo'), [
                'settings' => ['seo.meta_title_vi' => 'Tiêu đề mặc định'],
            ])
            ->assertRedirect(route('admin.settings.index', ['group' => 'seo']));

        $this->assertDatabaseHas('settings', [
            'setting_key' => 'seo.indexing_enabled',
            'setting_value' => '0',
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }

    /** @param array<int, string> $permissionNames */
    private function userWithPermissions(string $roleName, array $permissionNames): User
    {
        $role = Role::create([
            'name' => $roleName,
            'is_admin' => true,
            'is_system' => false,
        ]);
        $role->permissions()->sync(Permission::whereIn('name', $permissionNames)->pluck('id'));

        return $this->userWithRole($roleName);
    }
}
