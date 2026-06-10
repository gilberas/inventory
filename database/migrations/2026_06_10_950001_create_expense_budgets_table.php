<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('category');
            $table->date('month');             // first day of month, e.g. 2026-06-01
            $table->decimal('budget_amount', 10, 2);
            $table->timestamps();

            // Hard Rule §5 — month substitutes for status as the primary filter column
            $table->index(['tenant_id', 'month']);

            // One budget per tenant+branch+category+month combination
            $table->unique(['tenant_id', 'branch_id', 'category', 'month'], 'budget_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_budgets');
    }
};
