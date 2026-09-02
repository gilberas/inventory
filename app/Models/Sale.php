<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Sale extends TenantModel
{
    use SoftDeletes;

    protected $table = 'sales';

    const STATUS_PENDING   = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_VOIDED    = 'voided';

    protected $fillable = [
        'branch_id',
        'cashier_id',
        'customer_id',
        'warehouse_id',
        'pos_session_id',
        'receipt_no',
        'total',
        'discount',
        'tax',
        'grand_total',
        'payment_method',
        'status',
    ];

    protected $casts = [
        'total'       => 'decimal:2',
        'discount'    => 'decimal:2',
        'tax'         => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        parent::booted();
        static::creating(function (Sale $sale) {
            if (empty($sale->receipt_no)) {
                // Use withoutGlobalScopes so count is globally unique (ensures receipt_no DB uniqueness)
                $count = static::withoutGlobalScopes()->whereDate('created_at', today())->count() + 1;
                $sale->receipt_no = 'RCP-' . now()->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
    }
}
