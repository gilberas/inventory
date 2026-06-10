<?php

namespace App\Notifications;

use App\Models\BranchTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StockTransferStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly BranchTransfer $transfer,
        public readonly string         $newStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $messages = [
            'approved'   => "Transfer from {$this->transfer->fromBranch?->name} has been approved and will be dispatched soon.",
            'rejected'   => "Transfer from {$this->transfer->fromBranch?->name} was rejected.",
            'dispatched' => "Stock has been dispatched from {$this->transfer->fromBranch?->name}. Please prepare to receive.",
            'received'   => "Stock transfer from {$this->transfer->fromBranch?->name} has been received by {$this->transfer->toBranch?->name}.",
        ];

        return [
            'transfer_id' => $this->transfer->id,
            'status'      => $this->newStatus,
            'message'     => $messages[$this->newStatus] ?? "Transfer status updated to {$this->newStatus}.",
        ];
    }
}
