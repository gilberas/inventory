<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly int $warehouseId,
        public readonly float $requested,
        public readonly float $available,
    ) {
        parent::__construct(
            "Insufficient stock for product #{$productId} in warehouse #{$warehouseId}: "
            . "requested {$requested}, available {$available}."
        );
    }
}
