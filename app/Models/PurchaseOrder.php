<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PurchaseOrder extends TenantModel
{
    use SoftDeletes;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'reference_no',
        'supplier_id',
        'warehouse_id',
        'branch_id',
        'requisition_id',
        'created_by',
        'approved_by',
        'status',
        'order_date',
        'expected_date',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'order_date'    => 'date',
        'expected_date' => 'date',
        'total_amount'  => 'decimal:2',
    ];

    // ── Individual status constants ───────────────────────────
    const STATUS_DRAFT              = 'DRAFT';
    const STATUS_ORDERED            = 'APPROVED';
    const STATUS_PENDING_APPROVAL   = 'PENDING_APPROVAL';
    const STATUS_APPROVED           = 'APPROVED';
    const STATUS_RECEIVED           = 'RECEIVED';
    const STATUS_PARTIALLY_RECEIVED = 'PARTIALLY_RECEIVED';
    const STATUS_CANCELLED          = 'CANCELLED';

    // ── Statuses array (for dropdowns / labels) ───────────────
    const STATUSES = [
        'DRAFT'              => 'Draft',
        'PENDING_APPROVAL'   => 'Pending Approval',
        'APPROVED'           => 'Approved',
        'RECEIVED'           => 'Received',
        'PARTIALLY_RECEIVED' => 'Partially Received',
        'CANCELLED'          => 'Cancelled',
    ];

    // ── Relationships ─────────────────────────────────────────

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function goodsReceivedNotes()
    {
        return $this->hasMany(GoodsReceivedNote::class, 'purchase_order_id');
    }

    public function requisition()
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', self::STATUS_PENDING_APPROVAL);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeReceived($query)
    {
        return $query->where('status', self::STATUS_RECEIVED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    // ── Helpers ───────────────────────────────────────────────

    public function recalculateTotal(): void
    {
        $this->update([
            'total_amount' => $this->items()
                ->sum(DB::raw('quantity_ordered * unit_cost')),
        ]);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    // ── Auto reference number ─────────────────────────────────

    protected static function booted(): void
    {
        parent::booted(); // TenantModel sets tenant_id first
        static::creating(function (PurchaseOrder $po) {
            if (empty($po->reference_no)) {
                $count = static::whereDate('created_at', today())->count() + 1;
                $po->reference_no = 'PO-' . now()->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}