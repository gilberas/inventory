<?php

namespace App\Models;

class BranchTransfer extends TenantModel
{
    protected $table = 'branch_transfers';

    protected $fillable = [
        'from_branch_id',
        'to_branch_id',
        'requested_by',
        'approved_by',
        'dispatched_at',
        'received_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'received_at'   => 'datetime',
    ];

    const STATUS_PENDING    = 'pending';
    const STATUS_APPROVED   = 'approved';
    const STATUS_DISPATCHED = 'dispatched';
    const STATUS_RECEIVED   = 'received';
    const STATUS_REJECTED   = 'rejected';

    // ── Relationships ─────────────────────────────────────────────────────────

    public function fromBranch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(BranchTransferItem::class, 'transfer_id');
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isPending(): bool    { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool   { return $this->status === self::STATUS_APPROVED; }
    public function isDispatched(): bool { return $this->status === self::STATUS_DISPATCHED; }
    public function isReceived(): bool   { return $this->status === self::STATUS_RECEIVED; }
    public function isRejected(): bool   { return $this->status === self::STATUS_REJECTED; }

    public function hasDiscrepancy(): bool
    {
        return $this->items->contains(fn (BranchTransferItem $item) => $item->hasDiscrepancy());
    }
}
