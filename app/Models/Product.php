<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Product extends TenantModel
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
        'supplier_id',
        'unit_id',
        'cost_price',
        'selling_price',
        'tax_rate',
        'minimum_stock',
        'reorder_level',
        'unit_of_measure',
        'expiry_date',
        'status',
        'track_expiry',
        'track_batch',
        'is_active',
    ];

    protected $casts = [
        'cost_price'    => 'decimal:2',
        'selling_price' => 'decimal:2',
        'tax_rate'      => 'decimal:2',
        'track_expiry'  => 'boolean',
        'track_batch'   => 'boolean',
        'is_active'     => 'boolean',
        'expiry_date'   => 'date',
    ];

    // ── Boot ─────────────────────────────────────────────────

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (self $product) {
            if (empty($product->sku)) {
                $product->sku = static::generateSku($product);
            }
            // Keep is_active in sync
            if (isset($product->status)) {
                $product->is_active = $product->status === 'active';
            }
        });

        static::updating(function (self $product) {
            if ($product->isDirty('status')) {
                $product->is_active = $product->status === 'active';
            } elseif ($product->isDirty('is_active')) {
                $product->status = $product->is_active ? 'active' : 'inactive';
            }
        });
    }

    private static function generateSku(self $product): string
    {
        $catCode  = 'GEN';
        if ($product->category_id) {
            $catName = DB::table('product_categories')
                ->where('id', $product->category_id)
                ->value('name');
            if ($catName) {
                $catCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $catName), 0, 3));
                if ($catCode === '') $catCode = 'GEN';
            }
        }

        $tenantId = $product->tenant_id;
        do {
            $sku = 'SKU-' . $catCode . '-' . strtoupper(substr((string) Str::ulid(), -6));
        } while (
            static::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('sku', $sku)
                ->exists()
        );

        return $sku;
    }

    // ── Accessors ─────────────────────────────────────────────

    public function getMarginPercentAttribute(): float
    {
        $selling = (float) $this->selling_price;
        if ($selling == 0) return 0.0;
        return round((($selling - (float) $this->cost_price) / $selling) * 100, 2);
    }

    // ── Relationships ────────────────────────────────────────

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
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
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
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
        return (float) ($this->stockBalances()
            ->where('warehouse_id', $warehouseId)
            ->value('quantity_available') ?? 0);
    }

    public function totalStock(): float
    {
        return (float) $this->stockBalances()->sum('quantity_available');
    }

    public function isLowStock(): bool
    {
        return $this->totalStock() > 0 && $this->totalStock() <= ($this->reorder_level ?: $this->minimum_stock);
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeLowStock($query)
    {
        return $query->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('stock_balances')
                ->whereColumn('stock_balances.product_id', 'products.id')
                ->whereRaw('stock_balances.quantity_available > 0')
                ->whereRaw('stock_balances.quantity_available <= products.reorder_level');
        });
    }

    public function scopeOutOfStock($query)
    {
        return $query->where(function ($q) {
            $q->whereDoesntHave('stockBalances')
              ->orWhereHas('stockBalances', function ($s) {
                  $s->havingRaw('SUM(quantity_available) = 0');
              });
        });
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('track_expiry', true)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days)->toDateString());
    }
}
