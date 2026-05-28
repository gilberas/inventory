<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();    // e.g. SO-20240101-0001
            $table->foreignId('customer_id')
                  ->constrained('customers');
            $table->foreignId('warehouse_id')
                  ->constrained('warehouses');
            $table->foreignId('created_by')
                  ->constrained('users');

            $table->enum('status', [
                'DRAFT',
                'CONFIRMED',
                'PROCESSING',
                'SHIPPED',
                'DELIVERED',
                'CANCELLED',
            ])->default('DRAFT');

            $table->date('order_date');
            $table->date('expected_delivery')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
