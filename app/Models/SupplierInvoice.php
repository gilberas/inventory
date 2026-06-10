<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierInvoice extends TenantModel
{
    use SoftDeletes;

    const STATUS_PENDING  = 'pending';
    const STATUS_PAID     = 'paid';
    const STATUS_PARTIAL  = 'partial';
    const STATUS_OVERDUE  = 'overdue';

    protected $fillable = [
        'supplier_id',
        'grn_id',
        'po_id',
        'invoice_number',
        'amount',
        'tax_amount',
        'due_date',
        'status',
        'discrepancy_flag',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'tax_amount'       => 'decimal:2',
        'due_date'         => 'date',
        'discrepancy_flag' => 'boolean',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function grn()
    {
        return $this->belongsTo(GoodsReceivedNote::class, 'grn_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }
}
