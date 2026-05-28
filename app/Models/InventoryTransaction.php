<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryTransaction extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_transactions';

    protected $fillable = [
        'transaction_type',
        'reference_no',
        'warehouse_id',
        'created_by',
        'notes',
        'transaction_date',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
    ];

    // ── Type constants ───────────────────────────────────────

    const TYPES = [
        'PURCHASE'     => 'Purchase',
        'SALE'         => 'Sale',
        'RETURN_IN'    => 'Return In',
        'RETURN_OUT'   => 'Return Out',
        'TRANSFER_IN'  => 'Transfer In',
        'TRANSFER_OUT' => 'Transfer Out',
        'ADJUSTMENT'   => 'Adjustment',
        'DAMAGE'       => 'Damage',
    ];

    const IN_TYPES = ['PURCHASE', 'RETURN_IN', 'TRANSFER_IN', 'ADJUSTMENT'];

    const OUT_TYPES = ['SALE', 'RETURN_OUT', 'TRANSFER_OUT', 'DAMAGE'];

    // ── Relationships ────────────────────────────────────────

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(InventoryTransactionItem::class, 'transaction_id');
    }

    // ── Helpers ──────────────────────────────────────────────

    public function isInbound(): bool
    {
        return in_array($this->transaction_type, self::IN_TYPES);
    }

    public function isOutbound(): bool
    {
        return in_array($this->transaction_type, self::OUT_TYPES);
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeOfType($query, string $type)
    {
        return $query->where('transaction_type', $type);
    }

    // ── Auto reference number ────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (InventoryTransaction $transaction) {
            if (empty($transaction->reference_no)) {
                $count = static::whereDate('created_at', today())->count() + 1;
                $transaction->reference_no = 'TXN-' . now()->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
