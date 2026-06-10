<?php

namespace App\Listeners;

use App\Events\LowStockDetected;
use App\Models\User;
use App\Notifications\LowStockNotification;

class HandleLowStockDetected
{
    public function handle(LowStockDetected $event): void
    {
        $tenantId  = $event->product->tenant_id;
        $branchId  = $event->warehouse->branch_id;

        // Notify storekeepers assigned to this branch's warehouse
        $storekeepers = User::where('tenant_id', $tenantId)
            ->where('branch_id', $event->warehouse->id)
            ->permission('inventory.adjust')
            ->get();

        // Notify business owner(s) of the tenant
        $owners = User::where('tenant_id', $tenantId)
            ->role('Super Admin')
            ->get();

        $recipients = $storekeepers->merge($owners)->unique('id');

        $notification = new LowStockNotification($event->product, $event->warehouse, $event->currentQty);

        foreach ($recipients as $user) {
            $user->notify($notification);
        }
    }
}
