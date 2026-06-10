<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // POS-specific additions — nullable so existing B2B rows remain valid
            // Note: tenant_id already added by 2026_06_10_100001_add_tenant_id_to_core_tables
            $table->string('method', 30)->nullable()->after('payment_method');  // cash/mpesa/airtel/tigo/halo/credit
            $table->string('reference', 150)->nullable()->after('method');      // external ref e.g. M-Pesa checkout ID
            $table->string('status', 20)->default('completed')->after('reference'); // pending/completed/failed

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropIndex(['tenant_id', 'status']);
            }
            $table->dropColumn(['method', 'reference', 'status']);
        });
    }
};
