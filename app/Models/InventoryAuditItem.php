<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryAuditItem extends Model
{
    protected $table = 'inventory_audit_items';

    protected $fillable = [
        'audit_id',
        'product_id',
        'system_qty',
        'physical_qty',
        'variance',
        'notes',
    ];

    protected $casts = [
        'system_qty'   => 'decimal:4',
        'physical_qty' => 'decimal:4',
        'variance'     => 'decimal:4',
    ];

    public function audit()
    {
        return $this->belongsTo(InventoryAudit::class, 'audit_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
