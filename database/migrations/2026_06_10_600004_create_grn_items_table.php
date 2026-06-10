<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grn_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_id')
                  ->constrained('goods_received_notes')
                  ->cascadeOnDelete();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->restrictOnDelete();
            $table->unsignedBigInteger('po_item_id')->nullable();
            $table->decimal('qty_received', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->index(['grn_id', 'product_id']);
            $table->foreign('po_item_id')
                  ->references('id')->on('purchase_order_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_items');
    }
};
