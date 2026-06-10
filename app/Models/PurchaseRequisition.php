<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequisition extends TenantModel
{
    use SoftDeletes;

    const STATUS_DRAFT             = 'draft';
    const STATUS_PENDING           = 'pending';
    const STATUS_APPROVED          = 'approved';
    const STATUS_REJECTED          = 'rejected';
    const STATUS_REVISION_REQUESTED = 'revision_requested';

    protected $fillable = ['branch_id', 'requested_by', 'status', 'notes'];

    public function items()
    {
        return $this->hasMany(PurchaseRequisitionItem::class, 'requisition_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'requisition_id');
    }
}
