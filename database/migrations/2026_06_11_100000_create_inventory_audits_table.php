<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->enum('status', ['initiated', 'counting', 'completed', 'posted'])->default('initiated');
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->date('audit_date');
            $table->timestamps();

            // Hard Rule §5: composite index
            $table->index(['tenant_id', 'status']);
            $table->index(['warehouse_id', 'status']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_audits');
    }
};
