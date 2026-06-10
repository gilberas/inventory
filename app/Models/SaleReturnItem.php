<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    protected $table = 'sale_return_items';

    protected $fillable = [
        'return_id',
        'sale_item_id',
        'product_id',
        'qty',
        'unit_price',
        'refund_amount',
    ];

    protected $casts = [
        'qty'           => 'decimal:4',
        'unit_price'    => 'decimal:4',
        'refund_amount' => 'decimal:2',
    ];

    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class, 'return_id');
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
