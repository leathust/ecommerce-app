<?php

namespace Database\Seeders;

use App\Services\SystemSettingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingSeeder extends Seeder
{
    /**
     * Seed only missing registry keys. Existing values are intentionally
     * preserved because they may have been customized by an administrator.
     */
    public function run(): void
    {
        $now = now();
        $rows = [];

        foreach (config('system_settings.settings', []) as $key => $definition) {
            $rows[] = [
                'setting_key' => $key,
                'setting_value' => $this->serializeDefault($definition),
                'updated_by' => null,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('settings')->insertOrIgnore($rows);
        }

        app(SystemSettingService::class)->forgetCache();
    }

    /** @param array<string, mixed> $definition */
    private function serializeDefault(array $definition): ?string
    {
        $value = $definition['default'] ?? null;

        if ($value === null) {
            return null;
        }

        return ($definition['type'] ?? null) === 'bool'
            ? ($value ? '1' : '0')
            : (string) $value;
    }
}
