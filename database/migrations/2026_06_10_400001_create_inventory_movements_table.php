<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('type');                            // stock_in, stock_out, adjustment, …
            $table->decimal('qty', 15, 4);                    // positive = in, negative = out
            $table->decimal('balance_after', 15, 4);          // snapshot of qty after this movement
            $table->decimal('unit_cost', 15, 4)->nullable();  // cost at time of movement (for FIFO layers)
            $table->string('reference_type')->nullable();      // polymorphic owner class name
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->text('notes')->nullable();
            // Append-only: created_at only — no updated_at, no soft deletes
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
            // Composite index per CLAUDE.md Hard Rule §5 (tenant + movement type)
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
