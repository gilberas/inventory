<?php

namespace App\Notifications;

use App\Models\BranchTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StockTransferDiscrepancy extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly BranchTransfer $transfer) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $discrepancies = $this->transfer->items
            ->filter(fn ($item) => $item->hasDiscrepancy())
            ->map(fn ($item) => [
                'product'       => $item->product?->name,
                'qty_dispatched' => $item->qty_dispatched,
                'qty_received'   => $item->qty_received,
                'difference'     => abs((float) $item->qty_dispatched - (float) $item->qty_received),
            ])
            ->values()
            ->toArray();

        return [
            'transfer_id'   => $this->transfer->id,
            'discrepancies' => $discrepancies,
            'message'       => "Quantity discrepancy detected in transfer #{$this->transfer->id} from {$this->transfer->fromBranch?->name}.",
        ];
    }
}
