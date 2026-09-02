<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class SaleReturn extends TenantModel
{
    use SoftDeletes;

    protected $table = 'sale_returns';

    protected $fillable = [
        'sale_id',
        'created_by',
        'reason',
        'status',
        'total_refund',
    ];

    protected $casts = [
        'total_refund' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        parent::booted();
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function items()
    {
        return $this->hasMany(SaleReturnItem::class, 'return_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
