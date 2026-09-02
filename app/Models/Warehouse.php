<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Warehouse extends TenantModel
{
    use SoftDeletes;

    protected $table = 'warehouses';

    protected $fillable = [
        'branch_id',
        'manager_id',
        'name',
        'code',
        'address',
        'capacity',
        'phone',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
        'capacity'   => 'integer',
    ];

    protected static function booted(): void
    {
        parent::booted();

        // Ensure only one default warehouse per tenant at a time
        static::saving(function (Warehouse $warehouse) {
            if ($warehouse->is_default && $warehouse->id) {
                static::where('id', '!=', $warehouse->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

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

    public function inventory()
    {
        return $this->hasMany(Inventory::class);
    }

    // ── Computed attributes ───────────────────────────────────

    public function getTotalStockValueAttribute(): float
    {
        return (float) DB::table('inventory')
            ->join('products', 'products.id', '=', 'inventory.product_id')
            ->where('inventory.warehouse_id', $this->id)
            ->whereNull('products.deleted_at')
            ->sum(DB::raw('inventory.quantity * products.cost_price'));
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
