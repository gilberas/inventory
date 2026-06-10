<?php

namespace App\Exceptions;

use RuntimeException;

class AuditInProgressException extends RuntimeException
{
    public function __construct(public readonly int $warehouseId)
    {
        parent::__construct(
            "Warehouse #{$warehouseId} has an active stock audit in progress. "
            . "All stock movements are blocked until the audit is posted."
        );
    }
}
