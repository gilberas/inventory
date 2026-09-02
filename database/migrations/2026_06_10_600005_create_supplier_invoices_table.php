<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('grn_id')->nullable();
            $table->unsignedBigInteger('po_id')->nullable();
            $table->string('invoice_number');
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->date('due_date');
            $table->string('status', 20)->default('pending'); // pending|paid|partial|overdue
            $table->boolean('discrepancy_flag')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']); // Hard Rule §5
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
            $table->foreign('grn_id')->references('id')->on('goods_received_notes')->nullOnDelete();
            $table->foreign('po_id')->references('id')->on('purchase_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
