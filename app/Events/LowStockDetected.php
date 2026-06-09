<?php

namespace App\Events;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStockDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly Warehouse $warehouse,
        public readonly float $currentQty,
    ) {}
}
