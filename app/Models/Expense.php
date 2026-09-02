<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends TenantModel
{
    use SoftDeletes;

    const STATUS_DRAFT            = 'draft';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED         = 'approved';
    const STATUS_REJECTED         = 'rejected';

    const BUILT_IN_CATEGORIES = [
        'Rent', 'Electricity', 'Water', 'Salaries', 'Fuel',
        'Marketing', 'Internet', 'Office Supplies', 'Maintenance',
        'Transport', 'Other',
    ];

    protected $fillable = [
        'tenant_id', 'branch_id', 'warehouse_id', 'created_by', 'approved_by',
        'reference_no', 'category', 'description', 'notes',
        'amount', 'receipt_path', 'status',
        'expense_date', 'approved_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'expense_date' => 'date',
        'approved_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Expense $expense) {
            if (empty($expense->reference_no)) {
                $count = static::withoutGlobalScopes()->whereDate('created_at', today())->count() + 1;
                $expense->reference_no = 'EXP-' . now()->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    // ── Relations ──────────────────────────────────────────────────────────────

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** branch = warehouse in MVP */
    public function branch()
    {
        return $this->belongsTo(Warehouse::class, 'branch_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING_APPROVAL);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
