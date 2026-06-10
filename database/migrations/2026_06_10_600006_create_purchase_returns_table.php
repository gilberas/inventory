<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('grn_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('created_by');
            $table->string('reference_no')->nullable()->unique();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('reason');
            $table->string('status', 20)->default('completed'); // completed|cancelled
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']); // Hard Rule §5
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
            $table->foreign('grn_id')->references('id')->on('goods_received_notes')->restrictOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')
                  ->constrained('purchase_returns')
                  ->cascadeOnDelete();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->restrictOnDelete();
            $table->decimal('qty', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};
