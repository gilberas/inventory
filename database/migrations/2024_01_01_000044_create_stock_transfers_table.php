<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('from_warehouse_id')
                  ->constrained('warehouses');
            $table->foreignId('to_warehouse_id')
                  ->constrained('warehouses');
            $table->foreignId('created_by')
                  ->constrained('users');
            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->enum('status', [
                'DRAFT', 'PENDING_APPROVAL', 'DISPATCHED', 'RECEIVED', 'CANCELLED',
            ])->default('DRAFT');
            $table->date('transfer_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')
                  ->constrained('stock_transfers')
                  ->cascadeOnDelete();
            $table->foreignId('product_id')
                  ->constrained('products');
            $table->decimal('quantity', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
