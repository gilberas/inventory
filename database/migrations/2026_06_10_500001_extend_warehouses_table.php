<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // tenant_id already exists (added by 2026_06_10_100001_add_tenant_id_to_core_tables)
        Schema::table('warehouses', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('tenant_id');
            $table->unsignedBigInteger('manager_id')->nullable()->after('branch_id');
            $table->integer('capacity')->nullable()->after('address');

            // Hard Rule §5 composite index (spec: tenant_id + branch_id)
            $table->index(['tenant_id', 'branch_id']);

            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
        });

        // MySQL only: relax global unique(code) → unique(tenant_id, code)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropUnique(['code']);
                $table->unique(['tenant_id', 'code']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['manager_id']);
            $table->dropIndex(['tenant_id', 'branch_id']);
            $table->dropColumn(['branch_id', 'manager_id', 'capacity']);
        });
    }
};
