<?php

namespace App\Notifications;

use App\Models\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExpenseRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Expense $expense,
        public readonly string  $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'expense_rejected',
            'expense_id' => $this->expense->id,
            'reference'  => $this->expense->reference_no,
            'reason'     => $this->reason,
            'message'    => "Your expense {$this->expense->reference_no} was rejected: {$this->reason}",
        ];
    }
}
