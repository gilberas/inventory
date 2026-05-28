<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $table = 'goods_receipt_items';

    protected $fillable = [
        'goods_receipt_id',
        'product_id',
        'quantity_received',
        'unit_cost',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:4',
        'unit_cost'         => 'decimal:2',
    ];

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getTotalCostAttribute(): float
    {
        return $this->quantity_received * $this->unit_cost;
    }
}
