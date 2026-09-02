<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends TenantModel
{
    use SoftDeletes;

    const STATUS_ACTIVE   = 'active';
    const STATUS_INACTIVE = 'inactive';

    protected $table = 'suppliers';

    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'address',
        'contact_person',
        'tin',
        'status',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function invoices()
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // ── Aging analysis ────────────────────────────────────────

    /**
     * Returns outstanding payable amounts bucketed by days overdue (TZS).
     *
     * Buckets:
     *   current    — due today or in the future
     *   days_30    — 1–30 days past due
     *   days_60    — 31–60 days past due
     *   days_90_plus — more than 60 days past due
     *
     * Paid / cancelled invoices are excluded.
     */
    public function getAgingAnalysis(): array
    {
        // ISO date strings (YYYY-MM-DD) compare lexicographically, which is timezone-safe.
        $today    = now()->toDateString();
        $invoices = SupplierInvoice::where('supplier_id', $this->id)
            ->where('status', '!=', SupplierInvoice::STATUS_PAID)
            ->get(['amount', 'due_date']);

        $result = [
            'current'      => 0.0,
            'days_30'      => 0.0,
            'days_60'      => 0.0,
            'days_90_plus' => 0.0,
        ];

        foreach ($invoices as $invoice) {
            // Normalise to date string whether the cast returns Carbon or a raw string.
            $dueDate = $invoice->due_date instanceof Carbon
                ? $invoice->due_date->toDateString()
                : (string) $invoice->due_date;

            $amount = (float) $invoice->amount;

            if ($dueDate >= $today) {
                $result['current'] += $amount;
            } else {
                // Use PHP timestamp diff so there are no Carbon mutation concerns.
                $daysOverdue = (int) round((strtotime($today) - strtotime($dueDate)) / 86400);
                if ($daysOverdue <= 30) {
                    $result['days_30'] += $amount;
                } elseif ($daysOverdue <= 60) {
                    $result['days_60'] += $amount;
                } else {
                    $result['days_90_plus'] += $amount;
                }
            }
        }

        return $result;
    }

    // ── Auto code generation ──────────────────────────────────

    protected static function booted(): void
    {
        parent::booted(); // TenantModel sets tenant_id first
        static::creating(function (Supplier $supplier) {
            if (empty($supplier->code)) {
                $last = static::withTrashed()->latest('id')->value('id') ?? 0;
                $supplier->code = 'SUP-' . str_pad($last + 1, 3, '0', STR_PAD_LEFT);
            }
            // Keep is_active in sync with status for backward compat
            if (empty($supplier->status)) {
                $supplier->status = self::STATUS_ACTIVE;
            }
            $supplier->is_active = ($supplier->status === self::STATUS_ACTIVE);
        });

        static::updating(function (Supplier $supplier) {
            if ($supplier->isDirty('status')) {
                $supplier->is_active = ($supplier->status === self::STATUS_ACTIVE);
            }
        });
    }
}
