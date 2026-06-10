<?php

namespace App\Models;

class InventoryAudit extends TenantModel
{
    protected $table = 'inventory_audits';

    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'status',
        'initiated_by',
        'approved_by',
        'audit_date',
    ];

    protected $casts = [
        'audit_date' => 'date',
    ];

    const STATUS_INITIATED = 'initiated';
    const STATUS_COUNTING  = 'counting';
    const STATUS_COMPLETED = 'completed';
    const STATUS_POSTED    = 'posted';

    // ── Relationships ─────────────────────────────────────────────────────────

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(InventoryAuditItem::class, 'audit_id');
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_INITIATED, self::STATUS_COUNTING, self::STATUS_COMPLETED]);
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    // ── Check if all items have been counted ──────────────────────────────────

    public function allItemsCounted(): bool
    {
        return $this->items->isNotEmpty()
            && $this->items->every(fn (InventoryAuditItem $i) => $i->physical_qty !== null);
    }
}
