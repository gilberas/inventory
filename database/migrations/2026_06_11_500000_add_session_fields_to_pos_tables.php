<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // WF-2: add shift summary columns to pos_sessions
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->decimal('opening_cash', 12, 2)->default(0)->after('branch_id');
            $table->decimal('closing_cash', 12, 2)->nullable()->after('opening_cash');
            $table->decimal('total_sales', 14, 2)->default(0)->after('closing_cash');
            $table->unsignedInteger('total_transactions')->default(0)->after('total_sales');
        });

        // WF-2: link sales to the POS session they were created in
        if (Schema::hasTable('sales') && !Schema::hasColumn('sales', 'pos_session_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->unsignedBigInteger('pos_session_id')->nullable()->after('cashier_id');
                $table->foreign('pos_session_id')->references('id')->on('pos_sessions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'pos_session_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropForeign(['pos_session_id']);
                $table->dropColumn('pos_session_id');
            });
        }

        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropColumn(['opening_cash', 'closing_cash', 'total_sales', 'total_transactions']);
        });
    }
};
