<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_attempt_id')->constrained()->restrictOnDelete();
            $table->string('event_key', 191)->unique();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique('payment_attempt_id', 'payment_email_deliveries_attempt_unique');
            $table->index(['status', 'updated_at'], 'payment_email_deliveries_status_updated_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_email_deliveries');
    }
};
