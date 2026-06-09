<?php

namespace App\Models;

class Inventory extends TenantModel
{
    protected $table = 'inventory';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'valuation_method',
        'unit_cost',
        'last_updated',
    ];

    protected $casts = [
        'quantity'     => 'decimal:4',
        'unit_cost'    => 'decimal:4',
        'last_updated' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
