<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->replaceOrderForeignKey('payment_attempts', 'restrict');
        $this->replaceOrderForeignKey('sepay_transactions', 'restrict');
        $this->replaceOrderForeignKey('payment_status_histories', 'restrict');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->replaceOrderForeignKey('payment_status_histories', 'cascade');
        $this->replaceOrderForeignKey('sepay_transactions', 'set null');
        $this->replaceOrderForeignKey('payment_attempts', 'cascade');
    }

    private function replaceOrderForeignKey(string $tableName, string $onDelete): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($onDelete): void {
            $table->dropForeign(['order_id']);
            $table->foreign('order_id')->references('id')->on('orders')->onDelete($onDelete);
        });
    }
};
