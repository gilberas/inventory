<?php

namespace App\Notifications;

use App\Models\BranchTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StockTransferSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly BranchTransfer $transfer) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'transfer_id' => $this->transfer->id,
            'from_branch' => $this->transfer->fromBranch?->name,
            'to_branch'   => $this->transfer->toBranch?->name,
            'items_count' => $this->transfer->items()->count(),
            'message'     => "New stock transfer request from {$this->transfer->fromBranch?->name} awaiting your approval.",
        ];
    }
}
