<?php

namespace App\Providers;

use App\Events\InventoryAdjusted;
use App\Events\SaleCompleted;
use App\Listeners\AwardLoyaltyPoints;
use App\Listeners\InvalidateDashboardCache;
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

        // Loyalty points — synchronous listener (Hard Rule §3 is Mail/Notification only)
        Event::listen(SaleCompleted::class, [AwardLoyaltyPoints::class, 'handle']);
    }
}
