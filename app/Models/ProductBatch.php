<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBatch extends Model
{
    use SoftDeletes;

    protected $table = 'product_batches';

    protected $fillable = [
        'product_id',
        'batch_number',
        'expiry_date',
        'manufacture_date',
        'quantity',
    ];

    protected $casts = [
        'expiry_date'      => 'date',
        'manufacture_date' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays($days))
            ->whereDate('expiry_date', '>=', now());
    }

    public function scopeExpired($query)
    {
        return $query
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now());
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }
}
