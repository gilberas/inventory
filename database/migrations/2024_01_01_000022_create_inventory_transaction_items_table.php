<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                  ->constrained('inventory_transactions')
                  ->cascadeOnDelete();
            $table->foreignId('product_id')
                  ->constrained('products');
            $table->foreignId('batch_id')
                  ->nullable()
                  ->constrained('product_batches')
                  ->nullOnDelete();
            $table->foreignId('warehouse_location_id')
                  ->nullable()
                  ->constrained('warehouse_locations')
                  ->nullOnDelete();

            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transaction_items');
    }
};
