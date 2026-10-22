<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->string('name_vi');
            $table->string('name_en');
            $table->string('url', 1024)->nullable();
            $table->string('location', 50)->default('header');
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(true);
        });
    }

    public function down(): void { Schema::dropIfExists('menus'); }
};
