<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->enum('valuation_method', ['weighted_avg', 'fifo', 'lifo'])->default('weighted_avg');
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'warehouse_id']);
            // Composite index per CLAUDE.md Hard Rule §5 (tenant_id + primary filter column)
            $table->index(['tenant_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
