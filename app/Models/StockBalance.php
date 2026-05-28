<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockBalance extends Model
{
    protected $table = 'stock_balances';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity_available',
        'quantity_reserved',
    ];

    protected $casts = [
        'quantity_available' => 'decimal:4',
        'quantity_reserved'  => 'decimal:4',
    ];

    // ── Relationships ─────────────────────────────────────────

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    // ── Computed attribute ────────────────────────────────────

    public function getQuantityOnHandAttribute(): float
    {
        return (float) $this->quantity_available - (float) $this->quantity_reserved;
    }

    // ── Stock helpers ─────────────────────────────────────────
    // Named addStock/removeStock (NOT increment/decrement) because
    // PHP 8+ forbids making a non-static parent method static.
    // Eloquent's Model::increment() is non-static — naming conflict is fatal.

    public static function addStock(int $productId, int $warehouseId, float $qty): void
    {
        static::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity_available' => 0, 'quantity_reserved' => 0]
        );

        DB::table('stock_balances')
            ->where('product_id',   $productId)
            ->where('warehouse_id', $warehouseId)
            ->increment('quantity_available', $qty);
    }

    public static function removeStock(int $productId, int $warehouseId, float $qty): void
    {
        DB::table('stock_balances')
            ->where('product_id',   $productId)
            ->where('warehouse_id', $warehouseId)
            ->decrement('quantity_available', $qty);
    }

    public static function reserveStock(int $productId, int $warehouseId, float $qty): void
    {
        DB::table('stock_balances')
            ->where('product_id',   $productId)
            ->where('warehouse_id', $warehouseId)
            ->increment('quantity_reserved', $qty);
    }

    public static function releaseStock(int $productId, int $warehouseId, float $qty): void
    {
        DB::table('stock_balances')
            ->where('product_id',   $productId)
            ->where('warehouse_id', $warehouseId)
            ->decrement('quantity_reserved', $qty);
    }

    public static function adjustStock(int $productId, int $warehouseId, float $qty): void
    {
        static::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity_available' => 0, 'quantity_reserved' => 0]
        );

        if ($qty >= 0) {
            DB::table('stock_balances')
                ->where('product_id',   $productId)
                ->where('warehouse_id', $warehouseId)
                ->increment('quantity_available', $qty);
        } else {
            DB::table('stock_balances')
                ->where('product_id',   $productId)
                ->where('warehouse_id', $warehouseId)
                ->decrement('quantity_available', abs($qty));
        }
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeLowStock($query)
    {
        return $query
            ->join('products', 'stock_balances.product_id', '=', 'products.id')
            ->whereRaw('stock_balances.quantity_available <= products.minimum_stock')
            ->select('stock_balances.*');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('quantity_available', '<=', 0);
    }
}