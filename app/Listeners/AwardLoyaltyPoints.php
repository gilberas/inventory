<?php

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Services\LoyaltyService;

class AwardLoyaltyPoints
{
    public function __construct(private LoyaltyService $loyaltyService) {}

    public function handle(SaleCompleted $event): void
    {
        if (!$event->sale->customer_id) {
            return;
        }

        $this->loyaltyService->earn($event->sale);
    }
}
