<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->text('cancel_reason')->nullable()->after('note');
            $table->timestamp('confirmed_at')->nullable()->after('updated_at');
            $table->timestamp('processing_at')->nullable()->after('confirmed_at');
            $table->timestamp('shipped_at')->nullable()->after('processing_at');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
            $table->timestamp('stock_deducted_at')->nullable()->after('cancelled_at');
            $table->timestamp('stock_restored_at')->nullable()->after('stock_deducted_at');
            $table->index(['status', 'created_at'], 'orders_status_created_at_index');
            $table->index(['payment_status', 'created_at'], 'orders_payment_status_created_at_index');
        });

        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at');
            $table->index(['order_id', 'created_at'], 'order_histories_order_created_index');
        });

        DB::table('orders')->select(['id', 'status', 'created_at'])->orderBy('id')->chunkById(500, function ($orders): void {
            $rows = $orders->map(fn ($order) => [
                'order_id' => $order->id, 'from_status' => null, 'to_status' => $order->status,
                'changed_by' => null, 'note' => 'Khởi tạo lịch sử từ dữ liệu đơn hàng hiện có.',
                'created_at' => $order->created_at ?? now(),
            ])->all();
            if ($rows !== []) { DB::table('order_status_histories')->insert($rows); }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_status_created_at_index');
            $table->dropIndex('orders_payment_status_created_at_index');
            $table->dropColumn(['cancel_reason', 'confirmed_at', 'processing_at', 'shipped_at', 'delivered_at', 'cancelled_at', 'stock_deducted_at', 'stock_restored_at']);
        });
    }
};
