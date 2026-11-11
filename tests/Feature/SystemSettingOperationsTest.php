<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SystemSettingConsistencyService;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemSettingOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_seeder_inserts_all_registry_defaults_and_is_idempotent(): void
    {
        $definitions = config('system_settings.settings');

        $this->seed(SystemSettingSeeder::class);

        $this->assertDatabaseCount('settings', count($definitions));
        $this->assertDatabaseHas('settings', [
            'setting_key' => 'payment.cod_enabled',
            'setting_value' => '1',
            'updated_by' => null,
        ]);
        $this->assertDatabaseHas('settings', [
            'setting_key' => 'seo.og_image',
            'setting_value' => null,
        ]);

        Setting::query()->where('setting_key', 'site.name_vi')->update([
            'setting_value' => 'Tên do quản trị viên đặt',
        ]);
        $this->seed(SystemSettingSeeder::class);

        $this->assertDatabaseCount('settings', count($definitions));
        $this->assertDatabaseHas('settings', [
            'setting_key' => 'site.name_vi',
            'setting_value' => 'Tên do quản trị viên đặt',
        ]);
    }

    public function test_seeder_clears_typed_settings_cache(): void
    {
        $cacheKey = config('system_settings.cache_key');
        Cache::forever($cacheKey, ['stale' => true]);

        $this->seed(SystemSettingSeeder::class);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_check_command_is_read_only_and_reports_missing_keys(): void
    {
        $this->artisan('settings:check')
            ->expectsOutputToContain('Key còn thiếu')
            ->expectsOutputToContain('Cài đặt hệ thống chưa nhất quán')
            ->assertExitCode(1);

        $this->assertDatabaseCount('settings', 0);
    }

    public function test_check_command_can_insert_only_missing_defaults(): void
    {
        Setting::query()->create([
            'setting_key' => 'site.name_vi',
            'setting_value' => 'Giữ nguyên tên hiện tại',
        ]);

        $this->artisan('settings:check', ['--fix-missing' => true])
            ->expectsOutputToContain('Đã bổ sung các key còn thiếu')
            ->expectsOutputToContain('Cài đặt hệ thống nhất quán')
            ->assertSuccessful();

        $this->assertDatabaseCount('settings', count(config('system_settings.settings')));
        $this->assertDatabaseHas('settings', [
            'setting_key' => 'site.name_vi',
            'setting_value' => 'Giữ nguyên tên hiện tại',
        ]);
    }

    public function test_consistency_check_reports_unknown_invalid_missing_and_orphaned_assets_without_mutating_them(): void
    {
        $this->seed(SystemSettingSeeder::class);
        Storage::disk('public')->put('system-settings/logo/orphan.png', 'orphan');

        Setting::query()->where('setting_key', 'commerce.low_stock_threshold')->update([
            'setting_value' => 'not-an-integer',
        ]);
        Setting::query()->where('setting_key', 'site.logo')->update([
            'setting_value' => 'system-settings/logo/missing.png',
        ]);
        Setting::query()->create([
            'setting_key' => 'legacy.unknown',
            'setting_value' => 'kept',
        ]);

        $result = app(SystemSettingConsistencyService::class)->inspect();

        $this->assertSame(['legacy.unknown'], $result['unknown_keys']);
        $this->assertContains('commerce.low_stock_threshold', $result['invalid_keys']);
        $this->assertSame(['site.logo'], $result['missing_assets']);
        $this->assertSame(['system-settings/logo/orphan.png'], $result['orphaned_assets']);
        $this->assertDatabaseHas('settings', [
            'setting_key' => 'legacy.unknown',
            'setting_value' => 'kept',
        ]);
        Storage::disk('public')->assertExists('system-settings/logo/orphan.png');
    }
}
