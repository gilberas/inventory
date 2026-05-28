<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'description',
        'category_id',
        'brand_id',
        'unit_id',
        'cost_price',
        'selling_price',
        'minimum_stock',
        'track_expiry',
        'track_batch',
        'is_active',
    ];

    protected $casts = [
        'cost_price'    => 'decimal:2',
        'selling_price' => 'decimal:2',
        'track_expiry'  => 'boolean',
        'track_batch'   => 'boolean',
        'is_active'     => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function batches()
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function stockBalances()
    {
        return $this->hasMany(StockBalance::class);
    }

    public function transactionItems()
    {
        return $this->hasMany(InventoryTransactionItem::class);
    }

    // ── Helpers ──────────────────────────────────────────────

    public function stockInWarehouse(int $warehouseId): float
    {
        return $this->stockBalances()
            ->where('warehouse_id', $warehouseId)
            ->value('quantity_available') ?? 0;
    }

    public function totalStock(): float
    {
        return $this->stockBalances()->sum('quantity_available');
    }

    public function isLowStock(): bool
    {
        return $this->totalStock() <= $this->minimum_stock;
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereHas('stockBalances', function ($q) {
            $q->whereRaw('quantity_available <= products.minimum_stock');
        });
    }

    // ── Auto SKU ─────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->sku)) {
                $last = static::withTrashed()->latest('id')->value('id') ?? 0;
                $product->sku = 'PRD-' . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
            }
        });
    }
}
