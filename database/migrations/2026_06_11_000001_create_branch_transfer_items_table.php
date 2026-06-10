<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')
                  ->constrained('branch_transfers')
                  ->cascadeOnDelete();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->restrictOnDelete();
            $table->decimal('qty_requested', 15, 4);
            $table->decimal('qty_dispatched', 15, 4)->nullable();
            $table->decimal('qty_received', 15, 4)->nullable();
            $table->timestamps();

            $table->index(['transfer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_transfer_items');
    }
};
