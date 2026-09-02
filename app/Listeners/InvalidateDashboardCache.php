<?php

namespace App\Listeners;

use App\Events\InventoryAdjusted;
use App\Events\SaleCompleted;
use Illuminate\Support\Facades\Cache;

class InvalidateDashboardCache
{
    public function handleSaleCompleted(SaleCompleted $event): void
    {
        $this->bust($event->sale->tenant_id, $event->sale->warehouse_id);
    }

    public function handleInventoryAdjusted(InventoryAdjusted $event): void
    {
        $this->bust($event->tenantId, $event->warehouseId);
    }

    private function bust(int $tenantId, int $warehouseId): void
    {
        $today = today()->format('Y-m-d');
        // Invalidate both the specific-branch and the consolidated cache entries
        Cache::forget("tenant:{$tenantId}:dashboard:{$warehouseId}:{$today}");
        Cache::forget("tenant:{$tenantId}:dashboard:all:{$today}");
    }
}
