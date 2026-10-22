<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 100)->nullable()->unique()->after('id');
            $table->string('first_name', 100)->nullable()->after('password');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->string('phone', 30)->nullable()->after('last_name');
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active')->after('phone');
        });

        DB::table('users')->whereNull('username')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update(['username' => 'user-'.$user->id]);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE users MODIFY username VARCHAR(100) NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'first_name', 'last_name', 'phone', 'status']);
        });
    }
};
