<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->index()->after('id');
            $table->string('slug')->nullable()->after('name');
            $table->string('image_path')->nullable()->after('description');

            // Composite indexes (Hard Rule §5)
            $table->index(['tenant_id', 'parent_id'], 'product_categories_tenant_parent_idx');
        });

        // Composite unique on (tenant_id, slug) — MySQL/MariaDB only.
        // SQLite supports partial uniques but NULL handling differs; skip for tests.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->unique(['tenant_id', 'slug'], 'product_categories_tenant_slug_unique');
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('product_categories', function (Blueprint $table) {
                $table->dropUnique('product_categories_tenant_slug_unique');
            });
        }

        Schema::table('product_categories', function (Blueprint $table) {
            try { $table->dropIndex('product_categories_tenant_parent_idx'); } catch (\Exception) {}
            try { $table->dropIndex(['tenant_id']); } catch (\Exception) {}
            $table->dropColumn(['tenant_id', 'slug', 'image_path']);
        });
    }
};
