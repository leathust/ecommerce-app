<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('paid_at')->nullable()->after('payment_status');
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('environment', 20);
            $table->string('provider', 30)->default('sepay');
            $table->string('payment_code', 32);
            $table->string('idempotency_key', 64);
            $table->string('access_token_hash', 64)->nullable()->unique();
            $table->unsignedBigInteger('expected_amount');
            $table->char('currency', 3)->default('VND');
            $table->string('status', 30)->default('pending');
            $table->string('bank_code', 30);
            $table->string('bank_account_number', 100);
            $table->string('bank_account_name', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['environment', 'provider', 'payment_code'], 'payment_attempts_env_provider_code_unique');
            $table->unique(['environment', 'idempotency_key'], 'payment_attempts_env_idempotency_unique');
            $table->index(['order_id', 'status'], 'payment_attempts_order_status_index');
            $table->index(['status', 'expires_at'], 'payment_attempts_status_expires_index');
        });

        Schema::create('sepay_webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('environment', 20);
            $table->string('external_event_id', 191);
            $table->char('payload_hash', 64);
            $table->longText('raw_payload');
            $table->json('request_headers')->nullable();
            $table->string('status', 30)->default('received');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['environment', 'external_event_id'], 'sepay_receipts_env_event_unique');
            $table->unique(['environment', 'payload_hash'], 'sepay_receipts_env_hash_unique');
            $table->index(['status', 'received_at'], 'sepay_receipts_status_received_index');
        });

        Schema::create('sepay_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_receipt_id')->nullable()->constrained('sepay_webhook_receipts')->nullOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('environment', 20);
            $table->string('external_transaction_id', 191);
            $table->string('gateway', 50)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('direction', 10)->default('in');
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('VND');
            $table->text('transfer_content')->nullable();
            $table->timestamp('transaction_at');
            $table->string('match_status', 30)->default('unmatched');
            $table->string('match_reason', 100)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['environment', 'external_transaction_id'], 'sepay_transactions_env_external_unique');
            $table->index(['match_status', 'transaction_at'], 'sepay_transactions_match_time_index');
            $table->index(['order_id', 'transaction_at'], 'sepay_transactions_order_time_index');
        });

        Schema::create('payment_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sepay_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('source', 30);
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['order_id', 'created_at'], 'payment_histories_order_created_index');
            $table->index(['payment_attempt_id', 'created_at'], 'payment_histories_attempt_created_index');
        });

        Schema::create('payment_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('environment', 20);
            $table->string('status', 30)->default('pending');
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('checkpoint', 255)->nullable();
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['environment', 'status', 'created_at'], 'reconciliation_env_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_runs');
        Schema::dropIfExists('payment_status_histories');
        Schema::dropIfExists('sepay_transactions');
        Schema::dropIfExists('sepay_webhook_receipts');
        Schema::dropIfExists('payment_attempts');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('paid_at');
        });
    }
};
