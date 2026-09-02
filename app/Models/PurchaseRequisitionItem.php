<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Child records are tenant-scoped via their parent PurchaseRequisition.
class PurchaseRequisitionItem extends Model
{
    protected $table = 'purchase_requisition_items';

    protected $fillable = [
        'requisition_id',
        'product_id',
        'qty_requested',
        'suggested_supplier_id',
        'notes',
    ];

    protected $casts = [
        'qty_requested' => 'decimal:4',
    ];

    public function requisition()
    {
        return $this->belongsTo(PurchaseRequisition::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function suggestedSupplier()
    {
        return $this->belongsTo(Supplier::class, 'suggested_supplier_id');
    }
}
