<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds tenant_id (nullable, indexed) to every domain table so that
 * multi-tenant queries can filter by tenant without relying solely on
 * parent-FK chains. Nullable so existing single-tenant rows are preserved.
 */
return new class extends Migration
{
    private array $tables = [
        'products',
        'sales_orders',
        'purchase_orders',
        'customers',
        'suppliers',
        'warehouses',
        'stock_balances',
        'payments',
        'product_batches',
        'inventory_transactions',
        'stock_transfers',
        'activity_logs',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->index()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                // SQLite does not support dropIndex via ALTER; guard accordingly
                if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                    $table->dropIndex(["{$tableName}_tenant_id_index"]);
                }
                $table->dropColumn('tenant_id');
            });
        }
    }
};
