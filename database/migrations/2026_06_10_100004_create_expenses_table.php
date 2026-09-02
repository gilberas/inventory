<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('warehouse_id')->nullable();   // branch
            $table->foreignId('created_by')->constrained('users');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('reference_no')->unique();
            $table->string('category');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected'])->default('draft');
            $table->date('expense_date');
            $table->timestamps();
            $table->softDeletes();

            // Hard rule §5: composite index on (tenant_id, status)
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
