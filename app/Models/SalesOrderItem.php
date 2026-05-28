<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    protected $table = 'sales_order_items';

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount',
        'total_price',
    ];

    protected $casts = [
        'quantity'    => 'decimal:4',
        'unit_price'  => 'decimal:2',
        'discount'    => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Auto-calculate total_price before saving
    protected static function booted(): void
    {
        static::saving(function (SalesOrderItem $item) {
            $item->total_price = $item->quantity
                * $item->unit_price
                * (1 - ($item->discount ?? 0) / 100);
        });
    }
}
