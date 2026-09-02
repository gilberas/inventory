<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchTransferItem extends Model
{
    protected $table = 'branch_transfer_items';

    protected $fillable = [
        'transfer_id',
        'product_id',
        'qty_requested',
        'qty_dispatched',
        'qty_received',
    ];

    protected $casts = [
        'qty_requested'  => 'decimal:4',
        'qty_dispatched' => 'decimal:4',
        'qty_received'   => 'decimal:4',
    ];

    public function transfer()
    {
        return $this->belongsTo(BranchTransfer::class, 'transfer_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function hasDiscrepancy(): bool
    {
        if (is_null($this->qty_dispatched) || is_null($this->qty_received)) {
            return false;
        }

        return abs((float) $this->qty_dispatched - (float) $this->qty_received) > 0.0001;
    }
}
