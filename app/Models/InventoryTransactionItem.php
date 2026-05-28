<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransactionItem extends Model
{
    protected $table = 'inventory_transaction_items';

    protected $fillable = [
        'transaction_id',
        'product_id',
        'batch_id',
        'warehouse_location_id',
        'quantity',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'quantity'  => 'decimal:4',
        'unit_cost' => 'decimal:2',
    ];

    protected $appends = ['total_cost'];

    public function transaction()
    {
        return $this->belongsTo(InventoryTransaction::class, 'transaction_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function location()
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function getTotalCostAttribute(): float
    {
        return $this->quantity * $this->unit_cost;
    }
}
