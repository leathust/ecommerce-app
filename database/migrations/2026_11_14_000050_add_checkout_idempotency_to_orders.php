<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('checkout_idempotency_key', 64)->nullable()->unique()->after('order_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['checkout_idempotency_key']);
            $table->dropColumn('checkout_idempotency_key');
        });
    }
};
