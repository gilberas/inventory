<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();

            // Transaction type — every stock movement gets one
            $table->enum('transaction_type', [
                'PURCHASE',       // goods received from supplier
                'SALE',           // stock issued for a sale
                'RETURN_IN',      // customer returns stock
                'RETURN_OUT',     // stock returned to supplier
                'TRANSFER_IN',    // received from another warehouse
                'TRANSFER_OUT',   // sent to another warehouse
                'ADJUSTMENT',     // manual correction
                'DAMAGE',         // damaged/written off
            ]);

            $table->string('reference_no')->unique();   // e.g. TXN-20240101-0001
            $table->foreignId('warehouse_id')
                  ->constrained('warehouses');
            $table->foreignId('created_by')
                  ->constrained('users');

            $table->text('notes')->nullable();
            $table->timestamp('transaction_date');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['transaction_type', 'warehouse_id']);
            $table->index(['transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
