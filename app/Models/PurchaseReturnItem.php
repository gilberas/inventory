<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Child records are tenant-scoped via their parent PurchaseReturn.
class PurchaseReturnItem extends Model
{
    protected $table = 'purchase_return_items';

    protected $fillable = ['return_id', 'product_id', 'qty', 'unit_cost'];

    protected $casts = [
        'qty'       => 'decimal:4',
        'unit_cost' => 'decimal:4',
    ];

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class, 'return_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
