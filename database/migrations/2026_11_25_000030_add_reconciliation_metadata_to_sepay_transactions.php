<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sepay_transactions', function (Blueprint $table): void {
            $table->foreignId('reconciliation_run_id')
                ->nullable()
                ->after('webhook_receipt_id')
                ->constrained('payment_reconciliation_runs')
                ->nullOnDelete();
            $table->text('manual_note')->nullable()->after('match_reason');
            $table->foreignId('manual_action_by')
                ->nullable()
                ->after('manual_note')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('manual_action_at')->nullable()->after('manual_action_by');
            $table->index(['reconciliation_run_id', 'created_at'], 'sepay_transactions_reconciliation_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('sepay_transactions', function (Blueprint $table): void {
            $table->dropIndex('sepay_transactions_reconciliation_created_index');
            $table->dropConstrainedForeignId('manual_action_by');
            $table->dropConstrainedForeignId('reconciliation_run_id');
            $table->dropColumn(['manual_note', 'manual_action_at']);
        });
    }
};
