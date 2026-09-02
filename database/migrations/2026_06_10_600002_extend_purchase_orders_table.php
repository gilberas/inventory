<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('warehouse_id');
            $table->unsignedBigInteger('requisition_id')->nullable()->after('branch_id');

            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('requisition_id')->references('id')->on('purchase_requisitions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['requisition_id']);
            $table->dropColumn(['branch_id', 'requisition_id']);
        });
    }
};
