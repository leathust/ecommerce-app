<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->string('link_type', 20)->default('url');
            $table->string('route_name', 100)->nullable();
            $table->string('target', 20)->default('_self');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(
                ['location', 'parent_id', 'status', 'sort_order'],
                'menus_published_tree_index',
            );
        });

        DB::table('menus')
            ->whereNull('url')
            ->orWhere('url', '')
            ->update(['link_type' => 'none']);
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->dropIndex('menus_published_tree_index');
            $table->dropColumn([
                'link_type',
                'route_name',
                'target',
                'created_at',
                'updated_at',
            ]);
        });
    }
};
