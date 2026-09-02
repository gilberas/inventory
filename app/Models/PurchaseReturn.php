<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'grn_id',
        'warehouse_id',
        'created_by',
        'reference_no',
        'total_amount',
        'reason',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        parent::booted();
        static::creating(function (PurchaseReturn $pr) {
            if (empty($pr->reference_no)) {
                $count = static::whereDate('created_at', today())->count() + 1;
                $pr->reference_no = 'RET-' . now()->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class, 'return_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function grn()
    {
        return $this->belongsTo(GoodsReceivedNote::class, 'grn_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
