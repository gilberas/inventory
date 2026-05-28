<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use SoftDeletes;

    protected $table = 'stock_transfers';

    protected $fillable = [
        'reference_no',
        'from_warehouse_id',
        'to_warehouse_id',
        'created_by',
        'approved_by',
        'status',
        'transfer_date',
        'notes',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    const STATUSES = [
        'PENDING'    => 'Pending',
        'IN_TRANSIT' => 'In Transit',
        'COMPLETED'  => 'Completed',
        'CANCELLED'  => 'Cancelled',
    ];

    // ── Relationships ────────────────────────────────────────

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
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
        return $this->hasMany(StockTransferItem::class);
    }

    // ── Auto reference number ────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (StockTransfer $transfer) {
            if (empty($transfer->reference_no)) {
                $count = static::whereDate('created_at', today())->count() + 1;
                $transfer->reference_no = 'TRF-' . now()->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
