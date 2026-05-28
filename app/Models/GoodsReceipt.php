<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    protected $table = 'goods_receipts';

    protected $fillable = [
        'reference_no',
        'purchase_order_id',
        'warehouse_id',
        'received_by',
        'inventory_transaction_id',
        'received_date',
        'notes',
    ];

    protected $casts = [
        'received_date' => 'date',
    ];

    // ── Relationships ────────────────────────────────────────

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function inventoryTransaction()
    {
        return $this->belongsTo(InventoryTransaction::class);
    }

    public function items()
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    // ── Auto reference number ────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (GoodsReceipt $receipt) {
            if (empty($receipt->reference_no)) {
                $count = static::whereDate('created_at', today())->count() + 1;
                $receipt->reference_no = 'GRN-' . now()->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
