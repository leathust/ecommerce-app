<?php

namespace Tests\Unit;

use App\Exceptions\IamOperationException;
use App\Models\AdminAuditLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\SystemSettingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class SystemSettingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Cache::flush();
    }

    public function test_registry_has_unique_complete_and_valid_definitions(): void
    {
        $groups = config('system_settings.groups');
        $definitions = config('system_settings.settings');
        $allowedTypes = ['string', 'textarea', 'bool', 'int', 'decimal', 'enum', 'email', 'url', 'image', 'secret'];
        $allowedStatuses = ['active_consumer', 'planned_consumer', 'infrastructure_only'];
        $allowedEmptyBehaviors = ['fallback', 'hide', 'disable'];

        $this->assertNotEmpty($groups);
        $this->assertNotEmpty($definitions);
        $this->assertSame(array_keys($definitions), array_values(array_unique(array_keys($definitions))));

        foreach ($definitions as $key => $definition) {
            $this->assertSame($key, $definition['key']);
            $this->assertLessThanOrEqual(150, strlen($key));
            $this->assertArrayHasKey($definition['group'], $groups);
            $this->assertContains($definition['type'], $allowedTypes);
            $this->assertContains($definition['runtime_status'], $allowedStatuses);
            $this->assertContains($definition['empty_behavior'], $allowedEmptyBehaviors);
            $this->assertNotEmpty($definition['label']);
            $this->assertNotEmpty($definition['description']);
            $this->assertNotEmpty($definition['consumer']);
            $this->assertFalse(Validator::make(
                ['value' => $definition['default']],
                ['value' => $definition['rules']],
            )->fails(), "Default value for {$key} must pass its registry rules.");

            if ($definition['public']) {
                $this->assertDoesNotMatchRegularExpression(
                    '/password|token|secret|api_key|credential/i',
                    $key,
                );
            }

            if ($definition['type'] === 'image') {
                $this->assertIsArray($definition['upload']);
                $this->assertStringStartsWith('system-settings/', $definition['upload']['directory']);
                $this->assertNotEmpty($definition['upload']['rules']);
                $this->assertStringNotContainsString('.svg', $definition['upload']['accept']);
            }
        }
    }

    public function test_defaults_casts_public_allowlist_and_unknown_keys_are_safe(): void
    {
        $service = app(SystemSettingService::class);

        $this->assertSame('VND', $service->get('commerce.currency'));
        $this->assertSame(5, $service->get('commerce.low_stock_threshold'));
        $this->assertTrue($service->get('payment.cod_enabled'));
        $this->assertSame('fallback', $service->get('unknown.key', 'fallback'));

        Setting::query()->insert([
            ['setting_key' => 'commerce.low_stock_threshold', 'setting_value' => '12', 'updated_by' => null],
            ['setting_key' => 'payment.cod_enabled', 'setting_value' => '0', 'updated_by' => null],
            ['setting_key' => 'commerce.default_shipping_fee', 'setting_value' => '15000.5', 'updated_by' => null],
        ]);
        $service->forgetCache();

        $this->assertSame(12, $service->get('commerce.low_stock_threshold'));
        $this->assertFalse($service->get('payment.cod_enabled'));
        $this->assertSame('15000.50', $service->get('commerce.default_shipping_fee'));
        $this->assertArrayHasKey('contact.email', $service->publicValues());
        $this->assertArrayNotHasKey('mail.contact_recipient', $service->publicValues());
        $this->assertArrayNotHasKey('payment.bank_account_number', $service->publicValues());

        $this->expectException(InvalidArgumentException::class);
        $service->get('unknown.key');
    }

    public function test_authorized_group_update_is_typed_cached_and_audited_once(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $service = app(SystemSettingService::class);

        $service->all();
        $updated = $service->updateGroup($actor, 'commerce', [
            'commerce.default_shipping_fee' => '15000.5',
            'commerce.low_stock_threshold' => 8,
        ]);

        $this->assertSame('15000.50', $updated['commerce.default_shipping_fee']);
        $this->assertSame(8, $updated['commerce.low_stock_threshold']);
        $this->assertSame('15000.50', $service->get('commerce.default_shipping_fee'));
        $this->assertDatabaseHas('settings', [
            'setting_key' => 'commerce.default_shipping_fee',
            'setting_value' => '15000.50',
            'updated_by' => $actor->id,
        ]);

        $audit = AdminAuditLog::where('action', 'settings.group_updated')->sole();
        $this->assertSame(Setting::class, $audit->subject_type);
        $this->assertNull($audit->subject_id);
        $this->assertSame('commerce', $audit->after_values['group']);
        $this->assertSame('15000.50', $audit->after_values['values']['commerce.default_shipping_fee']);
        $this->assertSame(8, $audit->after_values['values']['commerce.low_stock_threshold']);
        $this->assertSame(
            $audit->after_values,
            app(AdminAuditService::class)->safeForDisplay($audit->after_values),
        );

        $service->updateGroup($actor, 'commerce', [
            'commerce.default_shipping_fee' => '15000.50',
            'commerce.low_stock_threshold' => 8,
        ]);
        $this->assertDatabaseCount('admin_audit_logs', 1);
    }

    public function test_update_rejects_unauthorized_unknown_cross_group_and_invalid_values(): void
    {
        $service = app(SystemSettingService::class);
        $customer = User::factory()->create(['status' => 'active']);

        $this->expectIamException(fn () => $service->updateGroup($customer, 'site', [
            'site.name_vi' => 'Không được phép',
        ]));

        $actor = $this->userWithRole(Role::SUPER_ADMIN);

        try {
            $service->updateGroup($actor, 'site', ['contact.email' => 'support@example.test']);
            $this->fail('Cross-group setting must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('settings', $exception->errors());
        }

        try {
            $service->updateGroup($actor, 'contact', ['contact.email' => 'not-an-email']);
            $this->fail('Invalid setting value must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('value', $exception->errors());
        }

        try {
            $service->updateGroup($actor, 'mail', ['mail.contact_auto_reply_enabled' => true]);
            $this->fail('Contact auto reply must remain disabled until queue/retry is implemented.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('value', $exception->errors());
        }

        $this->expectException(InvalidArgumentException::class);
        $service->updateGroup($actor, 'unknown', []);
    }

    public function test_database_and_cache_remain_unchanged_when_audit_fails(): void
    {
        $actor = $this->userWithRole(Role::SUPER_ADMIN);
        $service = app(SystemSettingService::class);
        $cachedBefore = $service->all();

        $audit = $this->mock(AdminAuditService::class);
        $audit->shouldReceive('recordSystemEvent')->once()->andThrow(new RuntimeException('Audit unavailable'));
        $service = app(SystemSettingService::class);

        try {
            $service->updateGroup($actor, 'site', ['site.name_vi' => 'Tên mới']);
            $this->fail('Expected audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertDatabaseMissing('settings', ['setting_key' => 'site.name_vi']);
        $this->assertSame($cachedBefore, $service->all());
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }

    private function expectIamException(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected IAM operation exception.');
        } catch (IamOperationException) {
            $this->assertTrue(true);
        }
    }
}
