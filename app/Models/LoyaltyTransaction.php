<?php

namespace App\Models;

class LoyaltyTransaction extends TenantModel
{
    const UPDATED_AT = null;

    protected $table = 'loyalty_transactions';

    const TYPE_EARN   = 'earn';
    const TYPE_REDEEM = 'redeem';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'sale_id',
        'type',
        'points',
        'balance_after',
    ];

    protected $casts = [
        'points'       => 'integer',
        'balance_after' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
