<?php

namespace App\Models;

class InventoryMovement extends TenantModel
{
    protected $table = 'inventory_movements';

    // Append-only: only created_at, no updated_at
    const UPDATED_AT = null;

    const TYPES = [
        'stock_in', 'stock_out', 'adjustment',
        'transfer_in', 'transfer_out',
        'sale', 'purchase', 'return_in', 'return_out', 'damage',
    ];

    // Types that consume stock (used in FIFO/LIFO layer calculations)
    const OUT_TYPES = ['stock_out', 'sale', 'transfer_out', 'damage', 'return_out'];

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'type',
        'qty',
        'balance_after',
        'unit_cost',
        'reference_type',
        'reference_id',
        'user_id',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'qty'          => 'decimal:4',
        'balance_after'=> 'decimal:4',
        'unit_cost'    => 'decimal:4',
        'created_at'   => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
