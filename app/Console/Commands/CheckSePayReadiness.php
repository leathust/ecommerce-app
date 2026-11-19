<?php

namespace App\Console\Commands;

use App\Services\SePayReadinessService;
use Illuminate\Console\Command;

class CheckSePayReadiness extends Command
{
    protected $signature = 'sepay:check {--connect : Kiểm tra kết nối Redis thực tế}';

    protected $description = 'Kiểm tra cấu hình và hạ tầng SePay mà không hiển thị credential';

    public function handle(SePayReadinessService $readiness): int
    {
        $result = $readiness->inspect((bool) $this->option('connect'));

        $this->line('SePay: '.($result['enabled'] ? 'ENABLED' : 'DISABLED'));
        $this->line('Environment: '.$result['environment']);
        $this->line('Checkout QR: '.($result['checkout_ready'] ? 'READY' : 'NOT READY'));
        $this->line('Webhook: '.($result['webhook_ready'] ? 'READY' : 'NOT READY'));
        $this->line('Queue: '.($result['queue_ready'] ? 'READY' : 'NOT READY'));
        $this->line('Broadcast: '.($result['broadcast_ready'] ? 'READY' : 'NOT READY'));

        foreach ($result['checkout_checks'] as $name => $check) {
            $this->line(sprintf('[%s] checkout.%s: %s', $check['ok'] ? 'OK' : 'FAIL', $name, $check['message']));
        }

        foreach ($result['checks'] as $name => $check) {
            $this->line(sprintf('[%s] %s: %s', $check['ok'] ? 'OK' : 'FAIL', $name, $check['message']));
        }

        if (! $result['enabled'] && $result['ready']) {
            $this->warn('SePay đang tắt; command chỉ xác nhận dependencies và cấu hình nền an toàn.');
        }

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
