<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Items are tenant-scoped via their parent WarehouseTransfer; no direct tenant_id needed.
class WarehouseTransferItem extends Model
{
    protected $table = 'warehouse_transfer_items';

    protected $fillable = [
        'transfer_id',
        'product_id',
        'qty',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'qty'       => 'decimal:4',
        'unit_cost' => 'decimal:4',
    ];

    public function transfer()
    {
        return $this->belongsTo(WarehouseTransfer::class, 'transfer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
