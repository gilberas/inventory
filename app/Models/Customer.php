<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends TenantModel
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'phone',
        'email',
        'address',
        'type',
        'credit_limit',
        'balance',
        'loyalty_points',
        'is_active',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'credit_limit'   => 'decimal:2',
        'balance'        => 'decimal:2',
        'loyalty_points' => 'integer',
    ];

    protected $appends = ['overdue_balance'];

    protected static function booted(): void
    {
        parent::booted();
        static::creating(function (Customer $customer) {
            if (empty($customer->code)) {
                // withoutGlobalScopes → globally unique code, not per-tenant
                $last = static::withoutGlobalScopes()->withTrashed()->latest('id')->value('id') ?? 0;
                $customer->code = 'CUS-' . str_pad($last + 1, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    // ── Relations ──────────────────────────────────────────────────────────────

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function tags()
    {
        return $this->belongsToMany(CustomerTag::class, 'customer_tag_pivot', 'customer_id', 'tag_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function canPurchaseOnCredit(float $amount): bool
    {
        return ((float) $this->balance + $amount) <= (float) $this->credit_limit;
    }

    public function getOverdueBalanceAttribute(): float
    {
        if ((float) $this->balance <= 0) {
            return 0.0;
        }

        $hasOldUnpaid = Sale::where('customer_id', $this->id)
            ->where('payment_method', 'credit')
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('created_at', '<', now()->subDays(30))
            ->exists();

        return $hasOldUnpaid ? (float) $this->balance : 0.0;
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
