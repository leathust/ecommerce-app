<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('locale', 5)->default('vi');
            $table->string('source', 50)->default('home');
            $table->enum('status', ['subscribed', 'unsubscribed'])->default('subscribed');
            $table->timestamp('consented_at');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('unsubscribe_token_hash', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'newsletter_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
