<?php

namespace App\Console\Commands;

use App\Services\MenuTreeConsistencyService;
use Illuminate\Console\Command;

class CheckMenus extends Command
{
    protected $signature = 'menus:check';

    protected $description = 'Read-only check for menu depth, cycles, missing parents and cross-location branches';

    public function handle(MenuTreeConsistencyService $consistency): int
    {
        $result = $consistency->inspect();

        $this->line("Tổng menu: {$result['total']}; root: {$result['roots']}; depth lớn nhất: {$result['deepest']}.");
        $this->renderGroup('Vòng lặp', $result['cycles']);
        $this->renderGroup('Parent không tồn tại', $result['missing_parents']);
        $this->renderGroup('Sai location', $result['cross_location']);
        $this->renderGroup('Vượt depth', $result['depth_overflow']);

        if ($this->hasIssues($result)) {
            $this->error('Cây Menu chưa hợp lệ. Lệnh chỉ đọc và không tự sửa dữ liệu.');

            return self::FAILURE;
        }

        $this->info('Cây Menu hợp lệ với contract hiện tại.');

        return self::SUCCESS;
    }

    /** @param array<int, string> $items */
    private function renderGroup(string $label, array $items): void
    {
        $this->line("<info>{$label}:</info> ".count($items));

        foreach ($items as $item) {
            $this->line("  - {$item}");
        }
    }

    /** @param array<string, int|array<int, string>> $result */
    private function hasIssues(array $result): bool
    {
        return collect(['cycles', 'missing_parents', 'cross_location', 'depth_overflow'])
            ->contains(fn (string $key): bool => $result[$key] !== []);
    }
}
