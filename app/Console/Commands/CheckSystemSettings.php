<?php

namespace App\Console\Commands;

use App\Services\SystemSettingConsistencyService;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Console\Command;

class CheckSystemSettings extends Command
{
    protected $signature = 'settings:check
                            {--fix-missing : Insert missing registry defaults without changing existing rows}';

    protected $description = 'Check system-setting keys, stored values and managed assets';

    public function handle(SystemSettingConsistencyService $consistency): int
    {
        $result = $consistency->inspect();

        if ($this->option('fix-missing') && $result['missing_keys'] !== []) {
            $this->callSilent('db:seed', [
                '--class' => SystemSettingSeeder::class,
                '--force' => true,
            ]);
            $this->info('Đã bổ sung các key còn thiếu; không thay đổi giá trị hiện có.');
            $result = $consistency->inspect();
        }

        $this->renderGroup('Key còn thiếu', $result['missing_keys']);
        $this->renderGroup('Key không thuộc registry', $result['unknown_keys']);
        $this->renderGroup('Giá trị không hợp lệ', $result['invalid_keys']);
        $this->renderGroup('Ảnh được tham chiếu nhưng không tồn tại', $result['missing_assets']);
        $this->renderGroup('Ảnh managed không còn được tham chiếu', $result['orphaned_assets']);

        if ($this->hasIssues($result)) {
            $this->error('Cài đặt hệ thống chưa nhất quán. Lệnh không tự xóa hoặc ghi đè dữ liệu.');

            return self::FAILURE;
        }

        $this->info('Cài đặt hệ thống nhất quán với registry.');

        return self::SUCCESS;
    }

    /** @param array<int, string> $items */
    private function renderGroup(string $label, array $items): void
    {
        if ($items === []) {
            $this->line("<info>{$label}:</info> 0");

            return;
        }

        $this->warn("{$label}: ".count($items));

        foreach ($items as $item) {
            $this->line("  - {$item}");
        }
    }

    /** @param array<string, array<int, string>> $result */
    private function hasIssues(array $result): bool
    {
        return collect($result)->contains(static fn (array $items): bool => $items !== []);
    }
}
