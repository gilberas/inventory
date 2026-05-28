<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $table = 'warehouses';

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function locations()
    {
        return $this->hasMany(WarehouseLocation::class);
    }

    public function stockBalances()
    {
        return $this->hasMany(StockBalance::class);
    }

    public function inventoryTransactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Ensure only one default warehouse at a time
    protected static function booted(): void
    {
        static::saving(function (Warehouse $warehouse) {
            if ($warehouse->is_default) {
                static::where('id', '!=', $warehouse->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
