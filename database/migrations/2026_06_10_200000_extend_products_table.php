<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable()->after('brand_id');
            $table->decimal('tax_rate', 5, 2)->default(18)->after('selling_price');
            $table->unsignedInteger('reorder_level')->default(0)->after('minimum_stock');
            $table->string('unit_of_measure', 50)->default('unit')->after('reorder_level');
            $table->date('expiry_date')->nullable()->after('track_batch');
            $table->string('status', 20)->default('active')->after('expiry_date');

            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();

            // Composite indexes (Hard Rule §5)
            $table->index(['tenant_id', 'status'], 'products_tenant_status_idx');
            $table->index(['tenant_id', 'category_id'], 'products_tenant_category_idx');
        });

        // MySQL/MariaDB: replace global uniques with tenant-scoped composites.
        // SQLite tests keep the global unique (acceptable for test isolation).
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique(['sku']);
                $table->dropUnique(['barcode']);
                $table->unique(['tenant_id', 'sku'],     'products_tenant_sku_unique');
                $table->unique(['tenant_id', 'barcode'], 'products_tenant_barcode_unique');
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique('products_tenant_sku_unique');
                $table->dropUnique('products_tenant_barcode_unique');
                $table->unique('sku');
                $table->unique('barcode');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            try { $table->dropIndex('products_tenant_status_idx'); } catch (\Exception) {}
            try { $table->dropIndex('products_tenant_category_idx'); } catch (\Exception) {}
            $table->dropColumn(['supplier_id', 'tax_rate', 'reorder_level', 'unit_of_measure', 'expiry_date', 'status']);
        });
    }
};
