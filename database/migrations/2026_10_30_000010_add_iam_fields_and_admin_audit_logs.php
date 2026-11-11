<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SYSTEM_ADMIN_ROLES = [
        'super_admin',
        'admin',
        'catalog_manager',
        'order_manager',
        'customer_support',
    ];

    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('description');
            $table->boolean('is_system')->default(false)->after('is_admin');
            $table->index('is_admin', 'roles_is_admin_index');
        });

        DB::table('roles')
            ->whereIn('name', self::SYSTEM_ADMIN_ROLES)
            ->update(['is_admin' => true, 'is_system' => true]);

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false)->after('status');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
        });

        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 150);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id'], 'admin_audit_subject_index');
            $table->index(['actor_id', 'created_at'], 'admin_audit_actor_created_index');
            $table->index(['action', 'created_at'], 'admin_audit_action_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['must_change_password', 'last_login_at']);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropIndex('roles_is_admin_index');
            $table->dropColumn(['is_admin', 'is_system']);
        });
    }
};
