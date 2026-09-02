<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';

    const STATUS_PENDING   = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED    = 'failed';

    protected $fillable = [
        'tenant_id',
        'reference_no',
        'payable_type',
        'payable_id',
        'created_by',
        'payment_method',
        'method',
        'reference',
        'status',
        'amount',
        'payment_date',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    public function payable()
    {
        return $this->morphTo();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->reference_no)) {
                $count = static::whereDate('created_at', today())->count() + 1;
                $payment->reference_no = 'PAY-' . now()->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
            if (empty($payment->status)) {
                $payment->status = self::STATUS_COMPLETED;
            }
        });
    }
}
