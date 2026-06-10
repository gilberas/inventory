<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceivedNote extends TenantModel
{
    use SoftDeletes;

    const STATUS_DRAFT     = 'draft';
    const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'purchase_order_id',
        'received_by',
        'warehouse_id',
        'status',
        'reference_no',
        'notes',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();
        static::creating(function (GoodsReceivedNote $grn) {
            if (empty($grn->reference_no)) {
                $count = static::whereDate('created_at', today())->count() + 1;
                $grn->reference_no = 'GRN-' . now()->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function items()
    {
        return $this->hasMany(GrnItem::class, 'grn_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getGrnTotalAttribute(): float
    {
        return (float) $this->items->sum(fn($item) => $item->qty_received * $item->unit_cost);
    }
}
