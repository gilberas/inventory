<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('warehouse_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('qty', 15, 4);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['transfer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfer_items');
    }
};
