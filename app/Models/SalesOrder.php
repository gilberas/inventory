<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use SoftDeletes;

    protected $table = 'sales_orders';

    protected $fillable = [
        'reference_no',
        'customer_id',
        'warehouse_id',
        'created_by',
        'status',
        'order_date',
        'expected_delivery',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'order_date'        => 'date',
        'expected_delivery' => 'date',
        'total_amount'      => 'decimal:2',
    ];

    // ── Individual status constants ───────────────────────────
    const STATUS_DRAFT      = 'DRAFT';
    const STATUS_CONFIRMED  = 'CONFIRMED';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_SHIPPED    = 'SHIPPED';
    const STATUS_DELIVERED  = 'DELIVERED';
    const STATUS_CANCELLED  = 'CANCELLED';

    // ── Statuses array (for dropdowns / labels) ───────────────
    const STATUSES = [
        'DRAFT'      => 'Draft',
        'CONFIRMED'  => 'Confirmed',
        'PROCESSING' => 'Processing',
        'SHIPPED'    => 'Shipped',
        'DELIVERED'  => 'Delivered',
        'CANCELLED'  => 'Cancelled',
    ];

    // ── Relationships ─────────────────────────────────────────

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

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
        return $this->hasMany(SalesOrderItem::class);
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeShipped($query)
    {
        return $query->where('status', self::STATUS_SHIPPED);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    // ── Helpers ───────────────────────────────────────────────

    public function recalculateTotal(): void
    {
        $this->update([
            'total_amount' => $this->items()->sum('total_price'),
        ]);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    public function getBalanceDueAttribute(): float
    {
        return $this->total_amount - $this->total_paid;
    }

    // ── Auto reference number ─────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (SalesOrder $order) {
            if (empty($order->reference_no)) {
                $count = static::whereDate('created_at', today())->count() + 1;
                $order->reference_no = 'SO-' . now()->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}