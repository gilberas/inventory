<?php

namespace App\Providers;

use App\Events\ExpenseApproved;
use App\Events\GRNConfirmed;
use App\Events\InventoryAdjusted;
use App\Events\LowStockDetected;
use App\Events\SaleCompleted;
use App\Listeners\AwardLoyaltyPoints;
use App\Listeners\HandleLowStockDetected;
use App\Listeners\InvalidateDashboardCache;
use App\Listeners\InvalidateFinancialCache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Dashboard cache invalidation
        Event::listen(SaleCompleted::class,    [InvalidateDashboardCache::class, 'handleSaleCompleted']);
        Event::listen(InventoryAdjusted::class, [InvalidateDashboardCache::class, 'handleInventoryAdjusted']);

        // Financial cache invalidation (§5.12)
        Event::listen(SaleCompleted::class,  [InvalidateFinancialCache::class, 'handleSaleCompleted']);
        Event::listen(ExpenseApproved::class, [InvalidateFinancialCache::class, 'handleExpenseApproved']);
        Event::listen(GRNConfirmed::class,    [InvalidateFinancialCache::class, 'handleGRNConfirmed']);

        // Loyalty points — synchronous listener (Hard Rule §3 is Mail/Notification only)
        Event::listen(SaleCompleted::class, [AwardLoyaltyPoints::class, 'handle']);

        // Low stock alert → notify storekeepers + business owner (BO-2)
        Event::listen(LowStockDetected::class, [HandleLowStockDetected::class, 'handle']);
    }
}
