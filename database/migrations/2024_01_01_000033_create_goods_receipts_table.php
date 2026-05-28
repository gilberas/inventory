<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();    // e.g. GRN-20240101-0001
            $table->foreignId('purchase_order_id')
                  ->constrained('purchase_orders');
            $table->foreignId('warehouse_id')
                  ->constrained('warehouses');
            $table->foreignId('received_by')
                  ->constrained('users');
            $table->foreignId('inventory_transaction_id')
                  ->nullable()
                  ->constrained('inventory_transactions')
                  ->nullOnDelete();
            $table->date('received_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
