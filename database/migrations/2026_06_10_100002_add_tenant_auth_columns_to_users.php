<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends users table with multi-tenancy and auth-security columns.
 * branch_id FK points to warehouses (warehouse = branch for MVP).
 * 2FA columns (two_factor_secret, two_factor_recovery_codes) are
 * added by §5.1 once pragmarx/google2fa-laravel is installed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->index()->after('id');
            $table->unsignedBigInteger('branch_id')->nullable()->after('tenant_id'); // FK to warehouses
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('remember_token');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->unsignedSmallInteger('failed_login_count')->default(0)->after('last_login_at');
            $table->timestamp('locked_until')->nullable()->after('failed_login_count');

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropIndex(['users_tenant_id_status_index']);
            }
            $table->dropColumn([
                'tenant_id', 'branch_id', 'status',
                'last_login_at', 'failed_login_count', 'locked_until',
            ]);
        });
    }
};
