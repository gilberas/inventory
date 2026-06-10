<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('inventory_audits')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('system_qty', 15, 4);           // snapshot at initiation — never changed
            $table->decimal('physical_qty', 15, 4)->nullable(); // entered by storekeeper
            $table->decimal('variance', 15, 4)->default(0); // physical_qty - system_qty
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['audit_id', 'product_id']);
            $table->index(['audit_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_audit_items');
    }
};
