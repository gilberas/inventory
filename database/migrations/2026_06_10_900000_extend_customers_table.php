<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // tenant_id already exists (2026_06_10_100001); balance already exists (2026_06_10_100003)
            $table->enum('type', ['retail', 'wholesale'])->default('retail')->after('address');
            $table->decimal('credit_limit', 10, 2)->default(0)->after('type');
            $table->unsignedInteger('loyalty_points')->default(0)->after('credit_limit');

            // Composite indexes for tenant-scoped filtering (Hard Rule §5 — type substitutes for status)
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropIndex(['tenant_id', 'type']);
                $table->dropIndex(['tenant_id', 'phone']);
            }
            $table->dropColumn(['type', 'credit_limit', 'loyalty_points']);
        });
    }
};
