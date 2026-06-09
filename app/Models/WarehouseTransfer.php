<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseTransfer extends TenantModel
{
    use SoftDeletes;

    protected $table = 'warehouse_transfers';

    const STATUS_PENDING    = 'pending';
    const STATUS_APPROVED   = 'approved';
    const STATUS_DISPATCHED = 'dispatched';
    const STATUS_RECEIVED   = 'received';
    const STATUS_CANCELLED  = 'cancelled';

    protected $fillable = [
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'notes',
        'requested_by',
        'approved_by',
        'dispatched_at',
        'received_at',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'received_at'   => 'datetime',
    ];

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function items()
    {
        return $this->hasMany(WarehouseTransferItem::class, 'transfer_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
