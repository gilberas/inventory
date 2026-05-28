<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $table = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity_ordered',
        'quantity_received',
        'unit_cost',
    ];

    protected $casts = [
        'quantity_ordered'  => 'decimal:4',
        'quantity_received' => 'decimal:4',
        'unit_cost'         => 'decimal:2',
    ];

    protected $appends = ['total_cost', 'remaining_qty'];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getTotalCostAttribute(): float
    {
        return $this->quantity_ordered * $this->unit_cost;
    }

    public function getRemainingQtyAttribute(): float
    {
        return $this->quantity_ordered - $this->quantity_received;
    }
}
