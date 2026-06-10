<?php

namespace App\Models;

class ExpenseBudget extends TenantModel
{
    protected $table = 'expense_budgets';

    protected $fillable = [
        'branch_id',
        'category',
        'month',
        'budget_amount',
    ];

    protected $casts = [
        'month'         => 'date',
        'budget_amount' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Warehouse::class, 'branch_id');
    }
}
