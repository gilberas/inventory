<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Child records are tenant-scoped via their parent GoodsReceivedNote.
class GrnItem extends Model
{
    protected $table = 'grn_items';

    protected $fillable = [
        'grn_id',
        'product_id',
        'po_item_id',
        'qty_received',
        'unit_cost',
        'expiry_date',
    ];

    protected $casts = [
        'qty_received' => 'decimal:4',
        'unit_cost'    => 'decimal:4',
        'expiry_date'  => 'date',
    ];

    public function grn()
    {
        return $this->belongsTo(GoodsReceivedNote::class, 'grn_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function poItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'po_item_id');
    }
}
