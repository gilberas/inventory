<?php

namespace App\Models;

class PosSession extends TenantModel
{
    protected $table = 'pos_sessions';

    const STATUS_ACTIVE = 'active';
    const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'cashier_id',
        'branch_id',
        'opening_cash',
        'closing_cash',
        'total_sales',
        'total_transactions',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_cash'       => 'decimal:2',
        'closing_cash'       => 'decimal:2',
        'total_sales'        => 'decimal:2',
        'total_transactions' => 'integer',
        'opened_at'          => 'datetime',
        'closed_at'          => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'pos_session_id');
    }
}
