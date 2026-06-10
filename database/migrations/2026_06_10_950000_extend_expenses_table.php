<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // branch_id: FK to warehouses (branch = warehouse for MVP)
            $table->unsignedBigInteger('branch_id')->nullable()->after('tenant_id');
            // receipt_path: path relative to local disk
            $table->string('receipt_path')->nullable()->after('description');
            // approved_at: when the expense was approved/rejected
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            // notes: used for reject reason and other annotations
            $table->text('notes')->nullable()->after('receipt_path');

            // Composite index requested by spec (branch_id, expense_date)
            $table->index(['branch_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropIndex(['branch_id', 'expense_date']);
            }
            $table->dropColumn(['branch_id', 'receipt_path', 'approved_at', 'notes']);
        });
    }
};
