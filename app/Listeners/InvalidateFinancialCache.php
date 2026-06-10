<?php

namespace App\Listeners;

use App\Events\ExpenseApproved;
use App\Events\GRNConfirmed;
use App\Events\SaleCompleted;
use Illuminate\Support\Facades\Cache;

class InvalidateFinancialCache
{
    public function handleSaleCompleted(SaleCompleted $event): void
    {
        $this->bust($event->sale->tenant_id);
    }

    public function handleExpenseApproved(ExpenseApproved $event): void
    {
        $this->bust($event->expense->tenant_id);
    }

    public function handleGRNConfirmed(GRNConfirmed $event): void
    {
        $this->bust($event->grn->tenant_id);
    }

    // Bumping the version key causes all prior cache entries (which embed the
    // version in their key) to be ignored on the next read; they expire via TTL.
    public static function bust(int $tenantId): void
    {
        Cache::increment("tenant:{$tenantId}:report:financial:version");
    }
}
