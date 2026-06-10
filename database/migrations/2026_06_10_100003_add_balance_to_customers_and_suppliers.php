<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised balance column maintained by the payment-recording flow.
 * customers.balance  > 0 → outstanding receivable
 * suppliers.balance  > 0 → outstanding payable
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('balance', 15, 2)->default(0)->after('is_active');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('balance', 15, 2)->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('balance'));
        Schema::table('suppliers', fn (Blueprint $t) => $t->dropColumn('balance'));
    }
};
