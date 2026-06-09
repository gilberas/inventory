<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Category extends TenantModel
{
    use SoftDeletes;

    protected $table = 'product_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_path',
        'parent_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Boot ─────────────────────────────────────────────────

    protected static function booted(): void
    {
        parent::booted(); // registers TenantScope + tenant_id auto-fill

        static::creating(function (self $category) {
            if (empty($category->slug) && !empty($category->name)) {
                $category->slug = static::uniqueSlug($category->name, $category->tenant_id);
            }
        });

        static::updating(function (self $category) {
            if ($category->isDirty('name') && empty($category->slug)) {
                $category->slug = static::uniqueSlug($category->name, $category->tenant_id);
            }
        });
    }

    private static function uniqueSlug(string $name, ?int $tenantId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i    = 1;

        while (
            static::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    // ── Accessors ─────────────────────────────────────────────

    /**
     * 0 = root, 1 = child, 2 = grandchild (hard max).
     */
    public function getDepthAttribute(): int
    {
        if ($this->parent_id === null) {
            return 0;
        }

        $parent = $this->parent; // may trigger lazy load
        if ($parent === null || $parent->parent_id === null) {
            return 1;
        }

        return 2;
    }

    /**
     * Sum of (quantity_available × cost_price) for products in this
     * category and all its descendants.
     */
    public function getStockValueAttribute(): float
    {
        $ids = array_merge([$this->id], $this->collectDescendantIds());

        $value = DB::table('stock_balances')
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->whereIn('products.category_id', $ids)
            ->where('products.tenant_id', $this->tenant_id)
            ->whereNull('products.deleted_at')
            ->sum(DB::raw('stock_balances.quantity_available * products.cost_price'));

        return (float) $value;
    }

    // ── Relationships ─────────────────────────────────────────

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id')->withoutGlobalScopes();
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ──────────────────────────────────────────────

    public function isLeaf(): bool
    {
        if ($this->relationLoaded('children')) {
            return $this->children->isEmpty();
        }

        return $this->children()->count() === 0;
    }

    private function collectDescendantIds(): array
    {
        $ids      = [];
        $children = $this->children()->with('children')->get();

        foreach ($children as $child) {
            $ids[] = $child->id;
            foreach ($child->children as $grandchild) {
                $ids[] = $grandchild->id;
            }
        }

        return $ids;
    }
}
