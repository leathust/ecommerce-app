<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_banners', function (Blueprint $table): void {
            $table->id();
            $table->enum('placement', ['hero', 'discount']);
            $table->string('title_vi');
            $table->string('title_en');
            $table->string('eyebrow_vi')->nullable();
            $table->string('eyebrow_en')->nullable();
            $table->text('description_vi')->nullable();
            $table->text('description_en')->nullable();
            $table->string('button_vi')->nullable();
            $table->string('button_en')->nullable();
            $table->string('image_url', 1024)->nullable();
            $table->string('link_url', 512)->nullable();
            $table->enum('theme', ['purple', 'teal', 'orange', 'green', 'black', 'watch', 'airpods'])->default('purple');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['placement', 'status', 'starts_at', 'ends_at'], 'home_banners_publish_index');
            $table->index(['placement', 'sort_order', 'id'], 'home_banners_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_banners');
    }
};
